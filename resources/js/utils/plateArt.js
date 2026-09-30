// resources/js/utils/plateArt.js
// Exact port of the SVG plate art generation from index.html

export function seedOf(s) {
    let h = 2166136261;
    for (const ch of String(s)) {
        h = Math.imul(h ^ ch.charCodeAt(0), 16777619) >>> 0;
    }
    return h;
}

export function rngOf(s) {
    let a = seedOf(s);
    return () => {
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

export const R = (r, a, b) => a + r() * (b - a);

export function blob(r, rad, wob, cx = 100, cy = 100) {
    let d = '';
    for (let i = 0; i < 26; i++) {
        const a = (i / 26) * Math.PI * 2;
        const rr = rad + Math.sin(i * 2.3) * wob * 0.6 + R(r, -wob, wob) * 0.5;
        const x = cx + Math.cos(a) * rr;
        const y = cy + Math.sin(a) * rr;
        d += (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1);
    }
    return d + 'Z';
}

export function dots(r, n, rad, size, fill, op = 1) {
    let s = '';
    for (let i = 0; i < n; i++) {
        const a = R(r, 0, Math.PI * 2);
        const d = Math.sqrt(r()) * rad;
        s += `<circle cx="${(100 + Math.cos(a) * d).toFixed(1)}" cy="${(100 + Math.sin(a) * d).toFixed(1)}" r="${R(r, size * 0.6, size).toFixed(1)}" fill="${fill}" opacity="${op}"/>`;
    }
    return s;
}

// Map product name/category to visual art and color
export function getProductArt(product, categoryName = '') {
    const name = (product.name_en || '').toLowerCase();
    const cat = (categoryName || '').toLowerCase();

    if (name.includes('bread') || name.includes("man'oushe") || name.includes("ka'ek") || cat.includes('oven')) {
        let top = null;
        if (name.includes('zaatar') || name.includes("za'atar")) top = 'zaatar';
        if (name.includes('cheese')) top = 'cheese';
        return { art: name.includes('kaek') || name.includes("ka'ek") ? 'ring' : 'bread', color: '#c69a5c', top };
    }
    if (name.includes('hummus') || name.includes('mutabbal') || name.includes('labneh') || name.includes('foul') || name.includes('balila') || cat.includes('cold mezze') || cat.includes('soups')) {
        let c = '#d9c491';
        if (name.includes('mutabbal')) c = '#bdb392';
        if (name.includes('labneh')) c = '#eae2d0';
        if (name.includes('foul')) c = '#6f6a35';
        if (name.includes('adas') || name.includes('lentil')) c = '#d09a3c';
        return { art: 'dip', color: c };
    }
    if (name.includes('tabbouleh') || name.includes('fattoush') || name.includes('salad') || cat.includes('salad')) {
        return { art: 'salad', color: '#5c7a3a' };
    }
    if (name.includes('vine leaves') || name.includes('warak') || cat.includes('sandwiches') || name.includes('sandwich') || name.includes('mekliyeh')) {
        return { art: 'rolls', color: '#4c5a34' };
    }
    if (name.includes('kefta')) {
        return { art: 'kefta', color: '#7a3b26' };
    }
    if (name.includes('tawouk') || name.includes('skewer')) {
        return { art: 'skewer', color: '#c98b45' };
    }
    if (name.includes('chops')) {
        return { art: 'chops', color: '#8b4630' };
    }
    if (name.includes('mixed grill') || cat.includes('grill') || cat.includes('fire')) {
        return { art: 'mixed', color: '#8f4b2c' };
    }
    if (cat.includes('main') || name.includes('maqlubeh') || name.includes('sayadieh')) {
        return { art: 'salad', color: '#a06a34' };
    }
    if (name.includes('nayyeh') || name.includes('habra') || name.includes('frakeh') || cat.includes('nayyeh')) {
        return { art: 'dip', color: '#93332a' };
    }
    if (name.includes('knafeh') || name.includes('othmalieh')) {
        return { art: 'knafeh', color: '#d0752c' };
    }
    if (name.includes('baklava') || name.includes('sambousek')) {
        return { art: 'baklava', color: '#d9b163' };
    }
    if (name.includes('mahalabia') || name.includes('pudding')) {
        return { art: 'cream', color: '#f0e6da' };
    }
    if (cat.includes('hot drinks') || name.includes('coffee') || name.includes('tea') || name.includes('infusion')) {
        return { art: 'coffee', color: '#3a2115' };
    }
    if (cat.includes('shisha') || cat.includes('smoke') || name.includes('apple') || name.includes('grape') || name.includes('mint')) {
        return { art: 'shisha', color: '#6d4a2a' };
    }
    if (cat.includes('drink') || cat.includes('juice') || cat.includes('shake') || cat.includes('cocktail')) {
        let c = '#d7e3a6';
        if (name.includes('pomegranate') || name.includes('rumman')) c = '#8e1f2c';
        if (name.includes('orange') || name.includes('carrot')) c = '#e08a24';
        if (name.includes('berry') || name.includes('toot')) c = '#5a2340';
        if (name.includes('rose')) c = '#e4bcbf';
        return { art: 'drink', color: c };
    }

    // Default fallback
    const colors = ['#d9c491', '#5c7a3a', '#c98b45', '#a06a34', '#c69a5c', '#d0752c'];
    const idx = Math.abs(seedOf(name)) % colors.length;
    return { art: 'dip', color: colors[idx] };
}

export function inner(it, r) {
    const c = it.color || it.c || '#c69a5c';
    switch (it.art) {
        case 'dip':
            return `<path d="${blob(r, 54, 7)}" fill="${c}"/>
        <path d="M100 60 A40 40 0 1 1 62 108" fill="none" stroke="rgba(0,0,0,.16)" stroke-width="9" stroke-linecap="round"/>
        <ellipse cx="112" cy="86" rx="22" ry="15" fill="#8a7326" opacity=".55"/>
        ${dots(r, 7, 34, 4.5, '#efe6cf', 0.9)}${dots(r, 14, 46, 2.4, '#5f7a3c', 0.85)}${dots(r, 20, 50, 1.6, '#8d3720', 0.6)}`;
        case 'salad': {
            let s = '';
            for (let i = 0; i < 70; i++) {
                const a = R(r, 0, Math.PI * 2);
                const d = Math.sqrt(r()) * 52;
                const x = 100 + Math.cos(a) * d;
                const y = 100 + Math.sin(a) * d;
                const col = [c, '#6f8f42', '#3f5c2a', '#a52a2a', '#efe3c8', '#c2b280'][Math.floor(r() * 6)];
                s += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${R(r, 4, 9).toFixed(1)}" height="${R(r, 3, 6).toFixed(1)}" rx="1.6" fill="${col}" transform="rotate(${(r() * 360).toFixed(0)} ${x.toFixed(1)} ${y.toFixed(1)})"/>`;
            }
            return `<circle cx="100" cy="100" r="56" fill="#EFE9DA"/>${s}`;
        }
        case 'rolls': {
            let s = '';
            for (let i = 0; i < 7; i++) {
                const a = (i / 7) * Math.PI * 2;
                const x = 100 + Math.cos(a) * 32;
                const y = 100 + Math.sin(a) * 32;
                s += `<g transform="rotate(${((a * 180) / Math.PI + 90).toFixed(0)} ${x.toFixed(1)} ${y.toFixed(1)})"><rect x="${(x - 22).toFixed(1)}" y="${(y - 7).toFixed(1)}" width="44" height="14" rx="7" fill="${c}"/>
        <path d="M${(x - 18).toFixed(1)} ${y.toFixed(1)} h36" stroke="#33421f" stroke-width="1.4" opacity=".8"/></g>`;
            }
            return s + `<circle cx="100" cy="100" r="15" fill="#e8dc9a"/><path d="M100 85 V115 M85 100 H115" stroke="#c9b96a" stroke-width="1.4"/>`;
        }
        case 'bread': {
            const top = it.top;
            let s = `<circle cx="100" cy="100" r="63" fill="${c}"/>${dots(r, 50, 58, 3.2, '#8a6432', 0.35)}`;
            if (top === 'zaatar') s += `<path d="${blob(r, 46, 6)}" fill="#4e6130" opacity=".9"/>${dots(r, 30, 44, 2.2, '#2f3d1c', 0.8)}${dots(r, 10, 40, 2.6, '#c9a94b', 0.5)}`;
            if (top === 'cheese') s += `<path d="${blob(r, 46, 7)}" fill="#f3e6c4"/>${dots(r, 12, 40, 5, '#e7c98a', 0.7)}${dots(r, 8, 42, 3, '#b98b3f', 0.55)}`;
            return s + `<path d="M46 92 q18 -14 34 -2" fill="none" stroke="rgba(0,0,0,.25)" stroke-width="2.4" stroke-linecap="round"/>`;
        }
        case 'ring':
            return `<path d="M100 34 A66 66 0 1 1 99.9 34 Z M100 72 A28 28 0 1 0 100.1 72 Z" fill="${c}" fill-rule="evenodd"/>
        ${dots(r, 55, 58, 1.9, '#f2e6c9', 0.75)}<path d="M52 76 q22 -16 46 -6" fill="none" stroke="rgba(255,236,190,.35)" stroke-width="3"/>`;
        case 'kefta': {
            let s = '';
            for (let i = 0; i < 3; i++) {
                const y = 68 + i * 24;
                s += `<rect x="42" y="${y}" width="116" height="17" rx="8.5" fill="${c}"/>
        <rect x="42" y="${y}" width="116" height="6" rx="3" fill="#a15a38" opacity=".55"/>${dots(r, 6, 52, 2, '#2c1a12', 0.5)}`;
            }
            return s + `<path d="M100 142 q14 10 26 4" stroke="#6f8f42" stroke-width="4" fill="none" stroke-linecap="round"/>`;
        }
        case 'skewer': {
            let s = '';
            for (let k = 0; k < 3; k++) {
                const a = -24 + k * 24;
                s += `<g transform="rotate(${a} 100 100)"><rect x="34" y="98" width="132" height="3" rx="1.5" fill="#9a9a9a"/>`;
                for (let i = 0; i < 4; i++) {
                    s += `<rect x="${46 + i * 30}" y="86" width="26" height="27" rx="9" fill="${c}"/><rect x="${46 + i * 30}" y="86" width="26" height="9" rx="4" fill="#e3b775" opacity=".5"/>`;
                }
                s += '</g>';
            }
            return s + dots(r, 8, 54, 3, '#6f8f42', 0.8);
        }
        case 'chops': {
            let s = '';
            for (let i = 0; i < 4; i++) {
                const a = (i / 4) * Math.PI * 2 + 0.5;
                const x = 100 + Math.cos(a) * 25;
                const y = 100 + Math.sin(a) * 25;
                s += `<g transform="rotate(${((a * 180) / Math.PI).toFixed(0)} ${x.toFixed(1)} ${y.toFixed(1)})"><ellipse cx="${x}" cy="${y}" rx="24" ry="18" fill="${c}"/>
        <rect x="${x + 20}" y="${y - 3}" width="20" height="6" rx="3" fill="#e8ddc6"/><ellipse cx="${x}" cy="${y}" rx="14" ry="9" fill="#5e2b1e" opacity=".55"/></g>`;
            }
            return s + dots(r, 14, 52, 2.2, '#8d3720', 0.6);
        }
        case 'mixed':
            return (
                inner({ ...it, art: 'skewer' }, rngOf((it.id || 'mixed') + 'a')) +
                `<g transform="rotate(64 100 100)"><rect x="46" y="122" width="108" height="15" rx="7.5" fill="#7a3b26"/></g>
        ${dots(r, 16, 54, 2.4, '#6f8f42', 0.7)}`
            );
        case 'drink':
            return `<circle cx="100" cy="100" r="62" fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.25)" stroke-width="2"/>
        <circle cx="100" cy="100" r="54" fill="${c}"/>
        ${dots(r, 5, 40, 9, 'rgba(255,255,255,.28)')}
        <g transform="rotate(-18 100 100)"><ellipse cx="78" cy="76" rx="15" ry="10" fill="#4d7a35"/><path d="M78 76 h16" stroke="#2f5220" stroke-width="1.4"/></g>
        <rect x="112" y="46" width="7" height="82" rx="3.5" fill="#e6d6b8" opacity=".85" transform="rotate(14 115 90)"/>
        <path d="M62 78 a44 44 0 0 1 30 -22" fill="none" stroke="rgba(255,255,255,.4)" stroke-width="4" stroke-linecap="round"/>`;
        case 'knafeh': {
            let s = `<circle cx="100" cy="100" r="58" fill="${c}"/>`;
            for (let i = 0; i < 40; i++) {
                const a = (i / 40) * Math.PI * 2;
                s += `<path d="${(100 + Math.cos(a) * 22).toFixed(1)} ${(100 + Math.sin(a) * 22).toFixed(1)} L${(100 + Math.cos(a) * 57).toFixed(1)} ${(100 + Math.sin(a) * 57).toFixed(1)}" stroke="rgba(255,214,140,.5)" stroke-width="2.2" stroke-linecap="round"/>`;
            }
            return s + `<circle cx="100" cy="100" r="22" fill="#f2e3c0"/>${dots(r, 16, 20, 2.6, '#6f8f42', 0.95)}`;
        }
        case 'baklava': {
            let s = '';
            for (let i = 0; i < 5; i++) {
                const a = (i / 5) * Math.PI * 2;
                const x = 100 + Math.cos(a) * 28;
                const y = 100 + Math.sin(a) * 28;
                s += `<g transform="rotate(${((a * 180) / Math.PI + 45).toFixed(0)} ${x.toFixed(1)} ${y.toFixed(1)})"><rect x="${x - 19}" y="${y - 14}" width="38" height="28" rx="3" fill="${c}"/>
        <rect x="${x - 19}" y="${y - 14}" width="38" height="7" rx="3" fill="#efd79a" opacity=".7"/><circle cx="${x}" cy="${y}" r="4" fill="#6f8f42"/></g>`;
            }
            return s + dots(r, 10, 52, 1.8, '#f3e3b8', 0.6);
        }
        case 'cream':
            return `<circle cx="100" cy="100" r="56" fill="${c}"/><circle cx="100" cy="100" r="56" fill="none" stroke="rgba(0,0,0,.07)" stroke-width="10"/>
        ${dots(r, 9, 30, 3.2, '#6f8f42', 0.95)}<g><ellipse cx="118" cy="82" rx="11" ry="7" fill="#dda0a8" transform="rotate(24 118 82)"/>
        <ellipse cx="82" cy="118" rx="9" ry="6" fill="#d3919b" transform="rotate(-32 82 118)"/></g>`;
        case 'coffee':
            return `<circle cx="100" cy="100" r="46" fill="#efe7d6"/><circle cx="100" cy="100" r="38" fill="${c}"/>
        <path d="M72 88 a34 34 0 0 1 24 -16" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="4" stroke-linecap="round"/>
        <ellipse cx="128" cy="128" rx="14" ry="9" fill="#8d7a4e" transform="rotate(38 128 128)"/>${dots(r, 5, 30, 2.4, '#2a1a0f', 0.5)}`;
        case 'shisha':
            return `<circle cx="100" cy="100" r="58" fill="#4A423A"/><circle cx="100" cy="100" r="50" fill="${c}"/>
        <circle cx="100" cy="100" r="44" fill="#b9b4ab"/>${dots(r, 26, 38, 2.2, '#3a352e', 0.9)}
        <g><circle cx="86" cy="92" r="13" fill="#5a4034"/><circle cx="86" cy="92" r="9" fill="#e8721f" opacity=".85"/>
        <circle cx="114" cy="104" r="12" fill="#5a4034"/><circle cx="114" cy="104" r="8" fill="#f0913a" opacity=".8"/>
        <circle cx="96" cy="118" r="11" fill="#4d372c"/><circle cx="96" cy="118" r="7" fill="#d75f18" opacity=".7"/></g>`;
        default:
            return `<circle cx="100" cy="100" r="54" fill="${c}"/>`;
    }
}

export function plateSVG(product, categoryName = '') {
    const artConfig = getProductArt(product, categoryName);
    const item = { ...product, ...artConfig };
    const r = rngOf(product.id || product.name_en || 'dish');

    return `<svg viewBox="0 0 200 200" aria-hidden="true">
    <defs><filter id="fsh_${product.id}" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="2" stdDeviation="2.6" flood-color="#2E2D2B" flood-opacity=".38"/></filter></defs>
    <circle cx="100" cy="100" r="97" fill="#FDFBF6"/>
    <circle cx="100" cy="100" r="97" fill="none" stroke="rgba(46,45,43,.20)" stroke-width="1"/>
    <circle cx="100" cy="100" r="97" fill="none" stroke="#A67C33" stroke-opacity=".55" stroke-width="1.1"/>
    <circle cx="100" cy="100" r="84" fill="#F5F1E8"/>
    <circle cx="100" cy="100" r="70" fill="#FFFEFA"/>
    <circle cx="100" cy="100" r="70" fill="none" stroke="rgba(46,45,43,.10)" stroke-width="5"/>
    <g opacity=".9"><path d="M100 12 q7 6 0 12 q-7 -6 0 -12Z" fill="#A67C33" opacity=".9"/>
    <path d="M172 62 q7 5 1 11" fill="none" stroke="#A67C33" stroke-opacity=".6" stroke-width="1.4"/></g>
    <g filter="url(#fsh_${product.id})">${inner(item, r)}</g>
  </svg>`;
}

