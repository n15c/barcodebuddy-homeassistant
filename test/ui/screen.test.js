// Browsertest der Screen-Seite (Playwright/Chromium). Wird von test/run.sh gestartet.
const { chromium } = require('playwright');
const { exec } = require('child_process');
const fs = require('fs');

const WORK = process.env.WORK, URL = process.env.BB_URL;
const scan = (code) => new Promise(r => exec('php index.php ' + code, { cwd: WORK + '/app', env: process.env }, () => r()));
const grocy = () => JSON.parse(fs.readFileSync(WORK + '/state.json'));
let failed = 0;
const check = (what, ok, detail = '') => { if (!ok) failed++; console.log((ok ? '  ok   ' : '  FAIL ') + what + (ok ? '' : ' – ' + detail)); };
const tapKeys = async (p, keys) => { for (const k of keys) await p.tap(`.ha-keys button[aria-label="${k}"]`); };

(async () => {
    const browser = await chromium.launch();
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 800 }, hasTouch: true })).newPage();
    const errors = [], external = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('request', r => { if (!r.url().startsWith(URL) && !r.url().startsWith('data:')) external.push(r.url()); });

    await scan('BBUDDY-C');
    await page.goto(URL + '/screen.php');
    await page.waitForFunction(() => document.getElementById('mode').textContent === 'Verbrauchen', null, { timeout: 10000 });
    check('Live-Verbindung und Modusanzeige', true);

    // Kaufen -> Lagerort-Auswahl -> Standard-Lagerort
    await page.tap('.tile[data-mode="Purchase"]');
    await page.waitForSelector('.ha-loc', { timeout: 5000 });
    check('Kaufen öffnet die Lagerort-Auswahl', (await page.$$('.ha-loc-default')).length === 1);
    await page.tap('.ha-loc:has-text("Kühlschrank")');
    await page.waitForSelector('#ha-banner', { state: 'visible' });

    // Scan mit Fortschritt und MHD-Dialog
    await page.evaluate(() => {
        window.__states = [];
        new MutationObserver(() => window.__states.push(document.getElementById('result').getAttribute('data-state')))
            .observe(document.getElementById('result'), { attributes: true });
    });
    const pending = scan('4001234567890');
    await page.waitForSelector('#result[data-state="busy"]', { timeout: 5000 });
    check('„Wird verarbeitet“ während der Produktanlage', (await page.textContent('#result-title')).includes('verarbeitet'));
    await pending;
    await page.waitForSelector('.ha-keys', { timeout: 8000 });
    check('MHD-Dialog erscheint', (await page.textContent('.ha-name')) === 'Haferdrink');
    await tapKeys(page, ['0', '2', '.', '2', '7']);
    check('Nur Monat = erster Tag', (await page.textContent('.ha-preview')).includes('01.02.2027'));

    // Produkt bearbeiten aus dem Dialog: Mengeneinheit mit Umrechnung
    await page.tap('.ha-edit-link');
    await page.waitForSelector('.ha-edit .ha-choices');
    await page.tap('.ha-edit .ha-choices button:has-text("Stück")');
    check('Umrechnung wird abgefragt', await page.isVisible('.ha-factor.ha-on'));
    await page.tap('.ha-factor .ha-choices button:has-text("6")');
    await page.tap('.ha-edit .ha-save');
    await page.waitForSelector('.ha-edit', { state: 'detached', timeout: 5000 });
    const oat = grocy().products.find(p => p.name === 'Haferdrink');
    check('Mengeneinheit in Grocy geändert', oat && oat.qu_id_stock == 4, JSON.stringify(oat));
    await page.tap('.ha-save');
    await page.waitForSelector('.ha-keys', { state: 'detached', timeout: 5000 });
    const row = grocy().stock.filter(s => s.product_id == oat.id).pop();
    check('MHD gesetzt und Bestand umgerechnet', row.best_before_date === '2027-02-01' && Number(row.amount) === 6, JSON.stringify(row));

    // Menge korrigieren
    await page.tap('#ha-banner button');
    await page.waitForSelector('#ha-banner', { state: 'hidden' });
    await scan('4000000000001');
    await page.waitForFunction(() => document.getElementById('result-text').textContent === 'Milch', null, { timeout: 6000 });
    check('Taste „Produkt bearbeiten“ nach dem Einkauf', await page.isVisible('#result-edit'));
    await page.tap('#btn-qty');
    await page.waitForFunction(() => !document.getElementById('qty-ok').disabled, null, { timeout: 10000 });
    await page.tap('#keypad button:has-text("3")');
    await page.tap('#qty-ok');
    await page.waitForFunction(() => document.getElementById('result-title').textContent === 'Menge geändert', null, { timeout: 5000 });
    const milk = grocy().stock.filter(s => s.product_id == 1).pop();
    check('Menge in Grocy korrigiert', Number(milk.amount) === 3, JSON.stringify(milk));

    check('Keine Anfragen an fremde Server', external.length === 0, external.join(', '));
    check('Keine JavaScript-Fehler', errors.length === 0, errors.join(' | '));
    await browser.close();
    process.exit(failed ? 1 : 0);
})().catch(e => { console.error('  FAIL ' + e.message); process.exit(1); });
