// Datumserkennung des MHD-Dialogs (barcodebuddy/incl/js/haStorage.js), Stichtag 16.11.2026
const vm = require('vm'), fs = require('fs'), path = require('path'), assert = require('assert');
const FIXED = new Date(2026, 10, 16, 12, 0, 0).getTime();
class FixedDate extends Date {
    constructor(...a) { if (a.length === 0) super(FIXED); else super(...a); }
    static now() { return FIXED; }
}
const ctx = { window: {}, document: { readyState: 'loading', addEventListener() {} }, Date: FixedDate, console };
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../barcodebuddy/incl/js/haStorage.js'), 'utf8'), ctx);
const parse = ctx.window.haStorageParseDate;
const cases = [
    ['15.12.26', '2026-12-15'], ['15.12.2026', '2026-12-15'], ['151226', '2026-12-15'], ['15122026', '2026-12-15'],
    ['5.3.27', '2027-03-05'], ['15/12/26', '2026-12-15'],
    ['02.2026', '2026-02-01 Monat'], ['02.26', '2026-02-01 Monat'], ['0226', '2026-02-01 Monat'], ['022026', '2026-02-01 Monat'],
    ['12.2026', '2026-12-01 Monat'], ['01.27', '2027-01-01 Monat'],
    ['15.12', '2026-12-15 ohne Jahr'], ['1512', '2026-12-15 ohne Jahr'], ['05.06', '2027-06-05 ohne Jahr'], ['01.11', '2027-11-01 ohne Jahr'],
    ['31.02.27', null], ['13.2026', null], ['15.13.26', null], ['1', null], ['15.', null], ['15.12.2', null], ['1.2.3.4', null],
];
for (const [input, want] of cases) {
    const r = parse(input);
    const got = r ? r.iso + (r.month ? ' Monat' : r.noYear ? ' ohne Jahr' : '') : null;
    assert.strictEqual(got, want, `Eingabe ${input}`);
}
console.log(`  ok   Datumserkennung (${cases.length} Fälle)`);
