// Aufbereitung der Barcode-Buddy-Meldungen auf der Screen-Seite (barcodebuddy/screen.php)
const fs = require('fs'), path = require('path'), assert = require('assert');
const src = fs.readFileSync(path.join(__dirname, '../../barcodebuddy/screen.php'), 'utf8');
const start = src.indexOf('        var A = t.act;');
const end = src.indexOf('        // ------------------------------------------------------------ Ergebnis & Verlauf');
assert(start > 0 && end > start, 'describe() in screen.php nicht gefunden');
const t = { done: 'Erledigt', act: { purchase: 'Eingekauft', consume: 'Verbraucht', consumeAll: 'Alles verbraucht', spoiled: 'Als verdorben gebucht',
    open: 'Geöffnet', stock: 'Bestand', shopping: 'Auf die Einkaufsliste', noStock: 'Nicht vorrätig', weight: 'Gewicht eingeben',
    quantity: 'Menge geändert', mode: 'Modus', inStock: 'Bestand', noBestBefore: 'Kein Standard-MHD hinterlegt' } };
const describe = new Function('t', src.slice(start, end) + '\nreturn describe;')(t);
const cases = [
    ['Adding  1 Packung of Milch. In Stock: 3', { title: 'Eingekauft', name: 'Milch', detail: '1 Packung · Bestand: 3' }],
    ['Adding  2 Stück of Eier - WARNING: No default best before date set!. In Stock: 6', { title: 'Eingekauft', name: 'Eier', detail: '2 Stück · Bestand: 6 · Kein Standard-MHD hinterlegt' }],
    ['Consuming 1 Packung of Milch. In Stock: 2', { title: 'Verbraucht', name: 'Milch', detail: '1 Packung · Bestand: 2' }],
    ['Currently in stock: 3 Packung of Milch\\nLocation Kühlschrank: 2 Packung', { title: 'Bestand', name: 'Milch', detail: '3 Packung · Kühlschrank: 2 Packung' }],
    ['None in stock, not consuming: Milch', { title: 'Nicht vorrätig', name: 'Milch', detail: '', state: 'unknown' }],
    ['Set state to Purchase', { mode: 'Purchase' }],
    ['Irgendeine neue Meldung', { title: 'Erledigt', name: 'Irgendeine neue Meldung', detail: '' }],
];
for (const [input, want] of cases) assert.deepStrictEqual(describe(input), want, input);
console.log(`  ok   Meldungen der Screen-Seite (${cases.length} Fälle)`);
