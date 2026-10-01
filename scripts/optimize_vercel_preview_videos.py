#!/usr/bin/env python3
"""Reduce Vercel menu video payloads while preserving H.264 and clip duration."""

import shutil
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
VIDEO_DIR = ROOT / "vercel-preview/storage/products/optimized/videos"
MAX_DIMENSION = 360
CRF = 28


def probe_dimensions(ffprobe: str, path: Path) -> tuple[int, int]:
    result = subprocess.run(
        [
            ffprobe,
            "-v",
            "error",
            "-select_streams",
            "v:0",
            "-show_entries",
            "stream=width,height",
            "-of",
            "csv=s=x:p=0",
            str(path),
        ],
        check=True,
        capture_output=True,
        text=True,
    )
    width, height = result.stdout.strip().split("x", maxsplit=1)
    return int(width), int(height)


def optimize(ffmpeg: str, ffprobe: str, source: Path) -> tuple[int, int]:
    width, height = probe_dimensions(ffprobe, source)
    if max(width, height) <= MAX_DIMENSION:
        return source.stat().st_size, source.stat().st_size

    temporary = source.with_name(f".{source.stem}.tmp{source.suffix}")
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
        "scale=w='min(360,iw)':h='min(360,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2",
        "-map_metadata",
        "-1",
        "-c:v",
        "libx264",
        "-preset",
        "medium",
        "-crf",
        str(CRF),
        "-pix_fmt",
        "yuv420p",
        "-movflags",
        "+faststart",
        str(temporary),
    ]

    try:
        subprocess.run(command, check=True)
        optimized_width, optimized_height = probe_dimensions(ffprobe, temporary)
        if max(optimized_width, optimized_height) > MAX_DIMENSION:
            raise RuntimeError(f"Unexpected output dimensions for {source.name}")

        before = source.stat().st_size
        after = temporary.stat().st_size
        if after >= before:
            raise RuntimeError(f"Re-encoding did not reduce {source.name}")

        temporary.replace(source)
        return before, after
    finally:
        temporary.unlink(missing_ok=True)


def main() -> None:
    ffmpeg = shutil.which("ffmpeg")
    ffprobe = shutil.which("ffprobe")
    if not ffmpeg or not ffprobe:
        raise SystemExit("ffmpeg and ffprobe are required")
    if not VIDEO_DIR.is_dir():
        raise SystemExit(f"Video directory not found: {VIDEO_DIR}")

    videos = sorted(VIDEO_DIR.glob("*.mp4"))
    total_before = 0
    total_after = 0
    for video in videos:
        before, after = optimize(ffmpeg, ffprobe, video)
        total_before += before
        total_after += after
        print(f"{video.name}: {before / 1024:.0f} KB -> {after / 1024:.0f} KB")

    reduction = 100 * (1 - total_after / total_before) if total_before else 0
    print(
        f"Optimized {len(videos)} videos: {total_before / 1048576:.1f} MB -> "
        f"{total_after / 1048576:.1f} MB ({reduction:.0f}% smaller)"
    )


if __name__ == "__main__":
    main()
