#!/usr/bin/env python3
"""Create smaller WebP photos and silent H.264 product video copies."""

import argparse
import json
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit


IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png"}


def optimize_image(ffmpeg: str, source: Path, target: Path) -> None:
    target.parent.mkdir(parents=True, exist_ok=True)
    temporary = target.with_name(f".{target.stem}.tmp{target.suffix}")
    command = [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        "-nostdin",
        "-y",
        "-i",
        str(source),
        "-frames:v",
        "1",
        "-vf",
        "scale=w='min(960,iw)':h='min(960,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
        "-map_metadata",
        "-1",
        "-an",
        "-c:v",
        "libwebp",
        "-q:v",
        "79",
        "-compression_level",
        "5",
        str(temporary),
    ]
    try:
        subprocess.run(command, check=True)
        temporary.replace(target)
    finally:
        temporary.unlink(missing_ok=True)


def optimize_video(ffmpeg: str, source: Path, target: Path) -> None:
    target.parent.mkdir(parents=True, exist_ok=True)
    temporary = target.with_name(f".{target.stem}.tmp{target.suffix}")
    command = [
        ffmpeg,
        "-hide_banner",
        "-loglevel",
        "error",
        "-nostdin",
        "-y",
        "-i",
        str(source),
        "-map",
        "0:v:0",
        "-sn",
        "-dn",
        "-an",
        "-vf",
        "scale=w='min(720,iw)':h='min(720,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
        "-map_metadata",
        "-1",
        "-c:v",
        "libx264",
        "-preset",
        "medium",
        "-crf",
        "25",
        "-pix_fmt",
        "yuv420p",
        "-movflags",
        "+faststart",
        str(temporary),
    ]
    try:
        subprocess.run(command, check=True)
        temporary.replace(target)
    finally:
        temporary.unlink(missing_ok=True)


def optimized_url(value: str, field: str, products_dir: Path) -> str:
    parts = urlsplit(value)
    marker = "/storage/products/"
    offset = parts.path.find(marker)

    if offset < 0:
        return value

    source_relative = Path(parts.path[offset + len(marker):])
    if field == "video_url" and source_relative.parts[:1] == ("videos",):
        target_relative = Path("optimized/videos") / source_relative.name
    elif field in {"image_url", "video_poster_url"} and source_relative.suffix.lower() in IMAGE_EXTENSIONS:
        target_relative = Path("optimized/images") / source_relative.parent / source_relative.with_suffix(".webp").name
    else:
        return value

    if not (products_dir / target_relative).is_file():
        return value

    path = parts.path[:offset] + marker + target_relative.as_posix()
    return urlunsplit((parts.scheme, parts.netloc, path, parts.query, parts.fragment))


def rewrite_menu_json(menu_dir: Path, products_dir: Path) -> int:
    if not menu_dir.is_dir():
        return 0

    updated_urls = 0
    for path in menu_dir.rglob("*.json"):
        if path.name == "version.json":
            continue

        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            continue

        def update(value):
            nonlocal updated_urls
            if isinstance(value, dict):
                for key, entry in value.items():
                    if isinstance(entry, str) and key in {"image_url", "video_url", "video_poster_url"}:
                        optimized = optimized_url(entry, key, products_dir)
                        if optimized != entry:
                            value[key] = optimized
                            updated_urls += 1
                    else:
                        update(entry)
            elif isinstance(value, list):
                for entry in value:
                    update(entry)

        update(data)
        path.write_text(json.dumps(data, ensure_ascii=False, indent=4), encoding="utf-8")

    version = "optimized-" + datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    version_path = menu_dir / "version.json"
    if version_path.is_file():
        version_path.write_text(json.dumps({"version": version}, indent=4), encoding="utf-8")

    menu_path = menu_dir / "menu.json"
    if menu_path.is_file():
        try:
            menu = json.loads(menu_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            pass
        else:
            if isinstance(menu, dict):
                menu["version"] = version
                menu_path.write_text(json.dumps(menu, ensure_ascii=False, indent=4), encoding="utf-8")

    return updated_urls


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--products-dir", type=Path, required=True)
    parser.add_argument("--menu-dir", type=Path)
    args = parser.parse_args()

    ffmpeg = shutil.which("ffmpeg")
    if not ffmpeg:
        parser.error("ffmpeg is required to optimize product media")

    products_dir = args.products_dir.resolve()
    if not products_dir.is_dir():
        parser.error(f"product media directory does not exist: {products_dir}")

    jobs = []
    for source in sorted(products_dir.rglob("*")):
        if not source.is_file() or "optimized" in source.parts:
            continue

        relative = source.relative_to(products_dir)
        if source.suffix.lower() in IMAGE_EXTENSIONS:
            target = products_dir / "optimized" / "images" / relative.with_suffix(".webp")
            jobs.append(("image", source, target, optimize_image))
        elif source.suffix.lower() == ".mp4" and relative.parts[0] == "videos":
            target = products_dir / "optimized" / "videos" / source.name
            jobs.append(("video", source, target, optimize_video))

    original_bytes = optimized_bytes = skipped = 0
    for index, (kind, source, target, optimizer) in enumerate(jobs, start=1):
        if not target.exists():
            print(f"[{index}/{len(jobs)}] {kind}: {source.name}", flush=True)
            optimizer(ffmpeg, source, target)
        else:
            skipped += 1
        original_bytes += source.stat().st_size
        optimized_bytes += target.stat().st_size

    savings = 1 - optimized_bytes / original_bytes if original_bytes else 0
    menu_dir = args.menu_dir
    if menu_dir is None and len(products_dir.parents) > 3:
        possible_menu_dir = products_dir.parents[3] / "public" / "generated" / "menu"
        if possible_menu_dir.is_dir():
            menu_dir = possible_menu_dir

    rewritten_urls = rewrite_menu_json(menu_dir, products_dir) if menu_dir else 0
    print(
        f"Finished {len(jobs)} media files; skipped {skipped} existing variants. "
        f"Originals: {original_bytes / 1_000_000:.1f} MB; optimized copies: "
        f"{optimized_bytes / 1_000_000:.1f} MB; reduction: {savings:.1%}. "
        f"Updated {rewritten_urls} URLs in the local static menu.",
        flush=True,
    )


if __name__ == "__main__":
    main()
