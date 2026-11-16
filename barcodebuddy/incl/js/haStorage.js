/*
 * Home-Assistant-Add-on: Einlagerungsmodus fuer screen.php
 *
 * Lagerort waehlen -> alle Scans werden dort eingebucht -> pro Produkt wird das
 * Mindesthaltbarkeitsdatum (MHD) abgefragt. Ohne Eingabe bleibt das Standard-MHD.
 */
(function () {
    "use strict";

    var API = "./ha_storage.php";
    var POLL_MS = 1500;
    var state = null;
    var lang = "de";
    var shownId = null;
    var busy = false;

    var T = {
        de: {
            menu: "Einlagern …",
            defaultLocation: "Standard-Lagerort des Produkts",
            editProduct: "Produkt bearbeiten",
            editLocation: "Lagerort",
            editUnit: "Mengeneinheit",
            editFactor: "Umrechnung: 1 %old% =",
            editFactorHint: "Nötig, weil schon Bestand gebucht ist. Grocy rechnet den Bestand um.",
            editSaved: "Gespeichert",
            defaultLocationShort: "Standard-Lagerort",
            pickTitle: "Wo wird eingelagert?",
            storing: "Einlagern in",
            stop: "Beenden",
            open: "offen",
            bbTitle: "Mindestens haltbar bis",
            placeholder: "TT.MM.JJ",
            hint: "Aufgedrucktes Datum eintippen, z.B. 15.12.26, 151226 oder 02.2026",
            monthStart: "Monatsanfang",
            noYear: "Jahr ergänzt",
            past: "liegt in der Vergangenheit",
            clear: "Löschen",
            backspace: "Zeichen löschen",
            busyScan: "Weiterer Scan wird verarbeitet …",
            weekdays: ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"],
            quickTitle: "oder schnell wählen",
            save: "Speichern",
            never: "Läuft nicht ab",
            skip: "Überspringen",
            skipHint: "Standard behalten",
            asLast: "Wie zuletzt",
            invalid: "Datum nicht erkannt",
            more: "weitere warten",
            days: "Tage", week: "Woche", weeks: "Wochen", month: "Monat", months: "Monate", year: "Jahr",
            error: "Fehler",
            noLocations: "In Grocy sind keine Lagerorte angelegt.",
            cancel: "Abbrechen",
            amount: "Menge"
        },
        en: {
            menu: "Store items …",
            defaultLocation: "Product's default location",
            editProduct: "Edit product",
            editLocation: "Location",
            editUnit: "Quantity unit",
            editFactor: "Conversion: 1 %old% =",
            editFactorHint: "Needed because stock was already booked. Grocy converts the stock.",
            editSaved: "Saved",
            defaultLocationShort: "default location",
            pickTitle: "Where are you storing items?",
            storing: "Storing in",
            stop: "Stop",
            open: "open",
            bbTitle: "Best before",
            placeholder: "DD.MM.YY",
            hint: "Type the printed date, e.g. 15.12.26, 151226 or 02.2026",
            monthStart: "start of month",
            noYear: "year added",
            past: "is in the past",
            clear: "Clear",
            backspace: "Delete character",
            busyScan: "Processing another scan …",
            weekdays: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
            quickTitle: "or pick quickly",
            save: "Save",
            never: "Never expires",
            skip: "Skip",
            skipHint: "keep default",
            asLast: "Same as last",
            invalid: "Date not recognised",
            more: "more waiting",
            days: "days", week: "week", weeks: "weeks", month: "month", months: "months", year: "year",
            error: "Error",
            noLocations: "There are no locations in Grocy.",
            cancel: "Cancel",
            amount: "Amount"
        }
    };

    function t(key) {
        return (T[lang] || T.en)[key] || T.en[key] || key;
    }

    // ------------------------------------------------------------------ Hilfsfunktionen

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (key) {
            if (key === "text") node.textContent = attrs[key];
            else if (key === "onclick") node.addEventListener("click", attrs[key]);
            else node.setAttribute(key, attrs[key]);
        });
        (children || []).forEach(function (child) {
            if (child) node.appendChild(child);
        });
        return node;
    }

    // Icons als eingebettete Vektorgrafik (Material Icons, Apache License 2.0): keine Schriftzeichen
    // oder Emojis, die auf manchen Geraeten fehlen, und nichts, was nachgeladen werden muss
    var ICONS = {
        fridge: "M18 2.01L6 2c-1.1 0-2 .89-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.11-.9-1.99-2-1.99zM18 20H6v-9.02h12V20zm0-11H6V4h12v5zM8 5h2v3H8zm0 7h2v5H8z",
        close: "M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z",
        backspace: "M22 3H7c-.69 0-1.23.35-1.59.88L0 12l5.41 8.11c.36.53.9.89 1.59.89h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H7.07L2.4 12l4.66-7H22v14zm-11.59-2L14 13.41 17.59 17 19 15.59 15.41 12 19 8.41 17.59 7 14 10.59 10.41 7 9 8.41 12.59 12 9 15.59z"
    };
    var SVG_NS = "http://www.w3.org/2000/svg"; // XML-Namensraum, wird nicht geladen

    function icon(name) {
        var svg = document.createElementNS(SVG_NS, "svg");
        svg.setAttribute("viewBox", "0 0 24 24");
        svg.setAttribute("aria-hidden", "true");
        svg.setAttribute("class", "ha-icon");
        var path = document.createElementNS(SVG_NS, "path");
        path.setAttribute("d", ICONS[name]);
        svg.appendChild(path);
        return svg;
    }

    function request(method, action, body, query) {
        var options = {method: method, credentials: "same-origin", headers: {}};
        if (body !== undefined) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        }
        var url = API + "?action=" + encodeURIComponent(action);
        Object.keys(query || {}).forEach(function (key) {
            url += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(query[key]);
        });
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) throw new Error(data.error || ("HTTP " + response.status));
                return data;
            });
        });
    }

    function pad(n) {
        return (n < 10 ? "0" : "") + n;
    }

    function iso(date) {
        return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
    }

    function formatDate(isoDate) {
        if (!isoDate) return "";
        if (isoDate === "2999-12-31") return t("never");
        var p = isoDate.split("-");
        return lang === "de" ? p[2] + "." + p[1] + "." + p[0] : p[2] + "/" + p[1] + "/" + p[0];
    }

    function addDays(days) {
        var d = new Date();
        d.setDate(d.getDate() + days);
        return iso(d);
    }

    function addMonths(months) {
        var d = new Date();
        var day = d.getDate();
        d.setDate(1);
        d.setMonth(d.getMonth() + months);
        var last = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        d.setDate(Math.min(day, last));
        return iso(d);
    }

    function validDate(y, m, d) {
        if (y < 2000 || y > 2099) return null;
        var date = new Date(y, m - 1, d);
        return date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d ? iso(date) : null;
    }

    /** Nur Monat und Jahr aufgedruckt: erster Tag des Monats (02.2026 -> 01.02.2026) */
    function firstOfMonth(y, m) {
        return m >= 1 && m <= 12 ? validDate(y, m, 1) : null;
    }

    /** Tag und Monat ohne Jahr: naechstes Vorkommen (bis eine Woche zurueck gilt noch dieses Jahr) */
    function withoutYear(d, m) {
        var year = new Date().getFullYear();
        var date = validDate(year, m, d);
        var cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - 7);
        if (!date || new Date(year, m - 1, d) < cutoff) date = validDate(year + 1, m, d);
        return date;
    }

    function isRecent(isoDate) {
        return +isoDate.substr(0, 4) >= new Date().getFullYear() - 1;
    }

    /** Zwei Zahlen: Monat.Jahr (02.26, 02.2026) oder Tag.Monat ohne Jahr (15.12) */
    function parseTwo(a, b) {
        if (b.length === 4) {
            var monthLong = firstOfMonth(+b, +a);
            return monthLong ? {iso: monthLong, month: true} : null;
        }
        if (a.length > 2 || b.length > 2) return null;
        var month = firstOfMonth(2000 + +b, +a);
        if (month && isRecent(month)) return {iso: month, month: true};
        var dayMonth = withoutYear(+a, +b);
        if (dayMonth) return {iso: dayMonth, noYear: true};
        return month ? {iso: month, month: true} : null;
    }

    /**
     * Erkennt aufgedruckte Daten: 15.12.26, 15.12.2026, 151226, 15122026 (Tag),
     * 02.26, 02.2026, 0226, 022026 (Monat -> erster Tag), 15.12 (ohne Jahr -> naechstes Vorkommen).
     * Trennzeichen . / - , und Leerzeichen sind gleichwertig.
     * @return {{iso: string, month?: boolean, noYear?: boolean}|null}
     */
    function parseDate(input) {
        var s = (input || "").trim();
        if (!s) return null;
        var parts = s.split(/[.\/\-,\s]+/).filter(Boolean);
        if (!parts.length || !parts.every(function (p) { return /^\d+$/.test(p); })) return null;
        if (parts.length === 3) {
            var y = parts[2].length === 2 ? 2000 + +parts[2] : (parts[2].length === 4 ? +parts[2] : 0);
            var day = validDate(y, +parts[1], +parts[0]);
            return day && parts[0].length <= 2 && parts[1].length <= 2 ? {iso: day} : null;
        }
        if (parts.length === 2) return parseTwo(parts[0], parts[1]);
        if (parts.length > 3) return null;

        var d = parts[0], r;
        if (d.length === 8) {
            r = validDate(+d.substr(4, 4), +d.substr(2, 2), +d.substr(0, 2));
            return r ? {iso: r} : null;
        }
        if (d.length === 6) {
            r = validDate(2000 + +d.substr(4, 2), +d.substr(2, 2), +d.substr(0, 2));
            if (r) return {iso: r};
            r = firstOfMonth(+d.substr(2, 4), +d.substr(0, 2));
            return r ? {iso: r, month: true} : null;
        }
        if (d.length === 4) return parseTwo(d.substr(0, 2), d.substr(2, 2));
        return null;
    }

    /** true, solange die Eingabe noch vervollstaendigt werden kann (keine Fehlermeldung zeigen) */
    function isIncomplete(input) {
        var s = (input || "").trim();
        if (!s || /[.\/\-,\s]$/.test(s)) return true;
        var parts = s.split(/[.\/\-,\s]+/).filter(Boolean);
        var last = parts[parts.length - 1];
        if (parts.length === 1) return last.length < 4 || last.length === 5 || last.length === 7;
        return last.length === 1 || last.length === 3;
    }

    function longDate(isoDate) {
        var p = isoDate.split("-");
        var weekday = t("weekdays")[new Date(+p[0], +p[1] - 1, +p[2]).getDay()];
        return weekday + ", " + formatDate(isoDate);
    }

    // ------------------------------------------------------------------ Styles

    var css = "" +
        "#ha-banner{background:#16a34a;color:#fff;font-weight:bold;font-size:1.3em;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;" +
        "padding:10px 16px;display:none;align-items:center;justify-content:space-between;gap:12px}" +
        "#ha-banner.ha-fixed{position:fixed;top:0;left:0;right:0;z-index:5}" +
        "#ha-banner button{font:inherit;font-size:.85em;padding:10px 20px;min-height:48px;border:0;border-radius:12px;background:#fff;color:#166534}" +
        ".ha-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:20;background:rgba(0,0,0,.85);display:flex;align-items:center;" +
        "justify-content:center;padding:16px;box-sizing:border-box}" +
        ".ha-box{background:#fff;border-radius:8px;padding:20px;width:100%;max-width:640px;max-height:100%;overflow:auto;" +
        "box-sizing:border-box;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;text-align:center;color:#111827}" +
        ".ha-box h2{margin:0 0 6px;font-size:1.3em;color:#555}" +
        ".ha-name{font-size:1.9em;font-weight:bold;margin:4px 0}" +
        ".ha-sub{color:#666;margin-bottom:10px}" +
        ".ha-pic{max-width:120px;max-height:120px;margin:4px auto;display:block}" +
        ".ha-entry{display:grid;grid-template-columns:1fr;gap:14px;margin-top:8px;text-align:center}" +
        ".ha-display{position:relative;display:flex;align-items:center;justify-content:center;min-height:72px;padding:8px 56px;" +
        "border-radius:14px;background:#f3f4f6;border:2px solid #2563eb;font-size:2.4em;font-weight:800;letter-spacing:.04em;" +
        "font-variant-numeric:tabular-nums;box-sizing:border-box}" +
        ".ha-display.ha-empty{color:#9ca3af;font-weight:600;border-color:transparent}" +
        ".ha-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:44px;height:44px;border:0;border-radius:50%;" +
        "background:#e5e7eb;color:#374151;font-size:22px;line-height:44px;padding:0}" +
        ".ha-preview{font-size:1.25em;min-height:1.5em;margin:8px 0 4px;color:#15803d;font-weight:bold}" +
        ".ha-preview.ha-bad{color:#c62828}.ha-preview.ha-warn{color:#c2410c}.ha-preview.ha-neutral{color:#6b7280;font-weight:normal;font-size:1em}" +
        ".ha-keys{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px}" +
        ".ha-keys button{min-height:64px;border:0;border-radius:14px;background:#f3f4f6;color:#111827;font:inherit;font-size:1.8em;font-weight:700;" +
        "touch-action:manipulation;-webkit-tap-highlight-color:transparent}" +
        ".ha-keys button:active,.ha-quick button:active,.ha-actions button:active{transform:scale(.96)}" +
        ".ha-keys .ha-key-fn{background:#e5e7eb;font-size:1.5em}" +
        ".ha-quick-title{color:#6b7280;font-size:.95em;margin-bottom:6px}" +
        ".ha-quick{display:grid;grid-template-columns:1fr 1fr;gap:8px}" +
        ".ha-quick button,.ha-actions button,.ha-loc{font:inherit;font-weight:bold;font-size:1.1em;padding:12px 10px;min-height:56px;border:0;border-radius:12px;" +
        "background:#e3f2fd;color:#0d47a1;touch-action:manipulation}" +
        ".ha-quick .ha-wide{grid-column:1 / -1}" +
        "@media (min-width:760px){.ha-box.ha-bb{max-width:860px}.ha-entry{grid-template-columns:1.15fr 1fr;align-items:start}}" +
        ".ha-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;position:sticky;bottom:-20px;background:#fff;" +
        "padding:10px 0 14px;box-shadow:0 -10px 12px -10px rgba(0,0,0,.25)}" +
        "@media (max-width:560px),(max-height:700px){.ha-modal{padding:8px}.ha-box{padding:14px 14px 20px}" +
        ".ha-box h2{font-size:1.05em;margin-bottom:2px}.ha-name{font-size:1.45em;margin:2px 0}.ha-sub{margin-bottom:4px}" +
        ".ha-pic{max-width:64px;max-height:64px}.ha-display{min-height:58px;font-size:1.9em}.ha-preview{font-size:1.05em;margin:6px 0 2px}" +
        ".ha-keys{gap:6px;margin-top:6px}.ha-keys button{min-height:52px;font-size:1.55em}.ha-entry{gap:10px}" +
        ".ha-quick{grid-template-columns:repeat(3,1fr);gap:6px}.ha-quick button{min-height:44px;font-size:.95em;padding:8px 4px}" +
        ".ha-actions{margin-top:10px;padding-top:8px}.ha-actions button{min-height:48px;font-size:1em}.ha-actions .ha-save{min-height:54px;font-size:1.25em}}" +
        ".ha-actions button{flex:1 1 30%}" +
        ".ha-actions .ha-save{background:#16a34a;color:#fff;flex:1 1 100%;font-size:1.4em;min-height:64px}" +
        ".ha-actions .ha-save.ha-off{background:#86efac}" +
        ".ha-actions .ha-never{background:#fff3e0;color:#e65100}" +
        ".ha-actions .ha-skip{background:#eee;color:#333}" +
        ".ha-loc{display:block;width:100%;margin:8px 0;font-size:1.5em}" +
        ".ha-edit h3{margin:16px 0 8px;font-size:1em;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}" +
        ".ha-choices{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}" +
        ".ha-choices button{font:inherit;font-weight:600;font-size:1.05em;padding:12px 16px;min-height:52px;border:2px solid transparent;border-radius:12px;background:#f3f4f6;color:#111827}" +
        ".ha-choices button.ha-sel{border-color:#16a34a;background:#dcfce7;color:#166534}" +
        ".ha-factor{display:none;margin-top:14px;padding:12px;border-radius:14px;background:#eff6ff}" +
        ".ha-factor.ha-on{display:block}" +
        ".ha-factor-row{display:flex;align-items:center;justify-content:center;gap:10px;font-weight:700;font-size:1.2em;flex-wrap:wrap}" +
        ".ha-factor-row button{font:inherit;width:52px;height:52px;border:0;border-radius:12px;background:#dbeafe;color:#1e40af;font-size:1.4em}" +
        ".ha-factor-val{min-width:70px;font-size:1.5em}" +
        ".ha-edit-link{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:6px 12px;border:0;border-radius:999px;background:#eff6ff;color:#1d4ed8;font:inherit;font-size:.9em;font-weight:600}" +
        "@media (prefers-color-scheme: dark){.ha-choices button{background:#1f2937;color:#f3f4f6}.ha-choices button.ha-sel{background:#14532d;color:#bbf7d0}" +
        ".ha-factor{background:#1e293b}.ha-factor-row button{background:#1e3a8a;color:#dbeafe}.ha-edit-link{background:#1e3a8a;color:#dbeafe}}" +
        ".ha-loc.ha-loc-default{background:#f3f4f6;color:#374151;font-size:1.2em}" +
        ".ha-more{margin-top:10px;color:#666}" +
        ".ha-icon{width:1.2em;height:1.2em;fill:currentColor;vertical-align:middle;flex:none}" +
        "#ha-banner .ha-banner-text{display:flex;align-items:center;gap:10px}#ha-banner .ha-icon{width:1.3em;height:1.3em}" +
        ".ha-clear .ha-icon{width:24px;height:24px;vertical-align:-6px}.ha-keys .ha-icon{width:1.05em;height:1.05em}" +
        ".ha-busy{display:none;align-items:center;justify-content:center;gap:10px;margin-top:10px;color:#2563eb;font-weight:600}" +
        ".ha-busy.ha-on{display:flex}" +
        ".ha-spin{width:18px;height:18px;border-radius:50%;border:3px solid rgba(37,99,235,.25);border-top-color:#2563eb;animation:ha-spin .9s linear infinite}" +
        "@keyframes ha-spin{to{transform:rotate(360deg)}}" +
        ".ha-error{color:#c62828;margin-top:8px;min-height:1.2em}" +
        ".ha-box{border-radius:20px}" +
        "@media (prefers-color-scheme: dark){.ha-box,.ha-actions{background:#111827;color:#f3f4f6}.ha-box h2,.ha-sub,.ha-quick-title,.ha-more{color:#9ca3af}" +
        ".ha-display,.ha-keys button{background:#1f2937;color:#f3f4f6}.ha-keys .ha-key-fn,.ha-clear{background:#374151;color:#f3f4f6}" +
        ".ha-quick button,.ha-loc{background:#1e3a8a;color:#dbeafe}.ha-preview{color:#4ade80}.ha-preview.ha-warn{color:#fdba74}" +
        ".ha-actions .ha-skip{background:#374151;color:#f3f4f6}.ha-actions .ha-never{background:#7c2d12;color:#fed7aa}}";

    // ------------------------------------------------------------------ Banner

    var banner;

    function renderBanner() {
        if (!banner) return;
        var session = state && state.session;
        if (!session) {
            banner.style.display = "none";
            document.body.style.paddingTop = "";
            return;
        }
        banner.innerHTML = "";
        var open = state.pending.length ? " · " + state.pending.length + " " + t("open") : "";
        banner.appendChild(el("span", {"class": "ha-banner-text"}, [icon("fridge"),
            document.createTextNode(t("storing") + ": " + locationLabel(session) + open)]));
        banner.appendChild(el("button", {text: t("stop"), onclick: stopSession}));
        banner.style.display = "flex";
        if (banner.classList.contains("ha-fixed"))
            document.body.style.paddingTop = banner.offsetHeight + "px";
    }

    function stopSession() {
        request("POST", "stop", {}).then(refresh).catch(showGlobalError);
    }

    function showGlobalError(error) {
        alert(t("error") + ": " + error.message);
    }

    // ------------------------------------------------------------------ Lagerort-Auswahl

    function locationLabel(session) {
        return session.location_id ? session.location_name : t("defaultLocationShort");
    }

    var pickerOpen = false, starting = false;

    function openPicker() {
        if (typeof window.closeNav === "function") window.closeNav();
        if (pickerOpen) return;
        pickerOpen = true;
        request("GET", "locations").then(function (locations) {
            var modal = el("div", {"class": "ha-modal"});
            var box = el("div", {"class": "ha-box"}, [el("h2", {text: t("pickTitle")})]);
            function close() {
                modal.remove();
                pickerOpen = false;
            }
            function choose(id) {
                starting = true;
                request("POST", "start", {location_id: id}).then(function () {
                    close();
                    return refresh();
                }).catch(showGlobalError).then(function () {
                    starting = false;
                });
            }
            if (!locations.length) box.appendChild(el("p", {text: t("noLocations")}));
            locations.forEach(function (location) {
                box.appendChild(el("button", {"class": "ha-loc", text: location.name, onclick: function () {
                    choose(location.id);
                }}));
            });
            // Ohne festen Lagerort einkaufen: Grocy nimmt den Standard-Lagerort des Produkts, MHD wird trotzdem abgefragt
            box.appendChild(el("button", {"class": "ha-loc ha-loc-default", text: t("defaultLocation"), onclick: function () {
                choose(0);
            }}));
            box.appendChild(el("div", {"class": "ha-actions"}, [
                el("button", {"class": "ha-skip", text: t("cancel"), onclick: close})
            ]));
            modal.appendChild(box);
            document.body.appendChild(modal);
        }).catch(function (e) {
            pickerOpen = false;
            showGlobalError(e);
        });
    }

    /** Kauf-Modus ohne laufende Einlagerung: Lagerort waehlen lassen, damit das MHD abgefragt wird */
    window.haStorageEnsureSession = function () {
        if (pickerOpen || starting || (state && state.session)) return;
        openPicker();
    };

    // ------------------------------------------------------------------ Produkt bearbeiten

    /**
     * Lagerort und Mengeneinheit eines Produkts aendern (z.B. direkt nach der automatischen Anlage).
     * @param {number} [productId] ohne Angabe: Produkt des letzten Einkaufs
     * @param {function} [onDone] wird nach dem Speichern aufgerufen
     */
    window.haStorageEditProduct = function (productId, onDone) {
        request("GET", "product", undefined, productId ? {product: productId} : null).then(function (data) {
            var location = data.location_id, unit = data.qu_id, factor = 1;
            var modal = el("div", {"class": "ha-modal", style: "z-index:30"});
            var error = el("div", {"class": "ha-error"});
            var oldUnitName = (data.units.filter(function (u) { return u.id === data.qu_id; })[0] || {}).name || "";

            function choices(list, current, onPick) {
                var wrap = el("div", {"class": "ha-choices"});
                list.forEach(function (entry) {
                    var b = el("button", {"class": entry.id === current ? "ha-sel" : "", text: entry.name, onclick: function () {
                        Array.prototype.forEach.call(wrap.children, function (c) { c.classList.remove("ha-sel"); });
                        b.classList.add("ha-sel");
                        onPick(entry.id, entry.name);
                    }});
                    wrap.appendChild(b);
                });
                return wrap;
            }

            var factorValue = el("span", {"class": "ha-factor-val", text: "1"});
            var factorUnit = el("span", {});
            function setFactor(v) {
                factor = Math.max(0.001, Math.round(v * 1000) / 1000);
                factorValue.textContent = String(factor).replace(".", lang === "de" ? "," : ".");
            }
            var quick = el("div", {"class": "ha-choices", style: "margin-top:10px"});
            [1, 2, 4, 6, 10, 12, 100, 250, 500, 1000].forEach(function (v) {
                quick.appendChild(el("button", {text: String(v), onclick: function () { setFactor(v); }}));
            });
            var factorBox = el("div", {"class": "ha-factor"}, [
                el("div", {"class": "ha-factor-row"}, [
                    el("span", {text: t("editFactor").replace("%old%", oldUnitName)}),
                    el("button", {text: "−", "aria-label": "−", onclick: function () { setFactor(factor - 1); }}),
                    factorValue,
                    el("button", {text: "+", "aria-label": "+", onclick: function () { setFactor(factor + 1); }}),
                    factorUnit
                ]),
                quick,
                el("div", {"class": "ha-hint", style: "margin-top:8px", text: t("editFactorHint")})
            ]);

            var save = el("button", {"class": "ha-save", text: t("save"), onclick: function () {
                error.textContent = "";
                request("POST", "product", {
                    product_id: data.product_id, location_id: location, qu_id: unit, factor: unit !== data.qu_id ? factor : null
                }).then(function () {
                    modal.remove();
                    if (typeof onDone === "function") onDone();
                }).catch(function (e) {
                    error.textContent = t("error") + ": " + e.message;
                });
            }});

            var box = el("div", {"class": "ha-box ha-edit"}, [
                el("h2", {text: t("editProduct")}),
                el("div", {"class": "ha-name", text: data.name}),
                el("h3", {text: t("editLocation")}),
                choices(data.locations, data.location_id, function (id) { location = id; }),
                el("h3", {text: t("editUnit")}),
                choices(data.units, data.qu_id, function (id, name) {
                    unit = id;
                    factorUnit.textContent = name;
                    factorBox.classList.toggle("ha-on", id !== data.qu_id);
                }),
                factorBox,
                el("div", {"class": "ha-actions"}, [save, el("button", {"class": "ha-skip", text: t("cancel"), onclick: function () {
                    modal.remove();
                }})]),
                error
            ]);
            modal.appendChild(box);
            document.body.appendChild(modal);
        }).catch(showGlobalError);
    };

    // ------------------------------------------------------------------ MHD-Dialog

    var dialog = null;

    var keyHandler = null;

    function closeDialog() {
        if (dialog) dialog.remove();
        if (keyHandler) document.removeEventListener("keydown", keyHandler);
        dialog = null;
        keyHandler = null;
        shownId = null;
    }

    function showDialog(item) {
        closeDialog();
        shownId = item.id;
        var chosen = null;
        var typed = "";
        var replaceOnType = false; // nach einer Schnelltaste beginnt die naechste Ziffer neu

        var display = el("div", {"class": "ha-display ha-empty"});
        var displayText = el("span", {text: t("placeholder")});
        display.appendChild(displayText);
        display.appendChild(el("button", {"class": "ha-clear", "aria-label": t("clear"), title: t("clear"), onclick: function () {
            typed = "";
            update();
        }}, [icon("close")]));
        var preview = el("div", {"class": "ha-preview ha-neutral", text: t("hint")});
        var error = el("div", {"class": "ha-error"});
        var saveButton;

        function setPreview(cls, text) {
            preview.className = "ha-preview" + (cls ? " " + cls : "");
            preview.textContent = text;
        }

        /** Eingabe auswerten und Anzeige/Vorschau aktualisieren */
        function update() {
            displayText.textContent = typed || t("placeholder");
            display.classList.toggle("ha-empty", !typed);
            var parsed = parseDate(typed);
            chosen = parsed ? parsed.iso : null;
            error.textContent = "";
            if (!typed) {
                setPreview("ha-neutral", t("hint"));
            } else if (parsed) {
                var note = parsed.month ? " (" + t("monthStart") + ")" : (parsed.noYear ? " (" + t("noYear") + ")" : "");
                var past = parsed.iso < iso(new Date());
                setPreview(past ? "ha-warn" : "", "→ " + longDate(parsed.iso) + note + (past ? " – " + t("past") : ""));
            } else {
                setPreview(isIncomplete(typed) ? "ha-neutral" : "ha-bad", isIncomplete(typed) ? "…" : t("invalid"));
            }
            if (saveButton) saveButton.classList.toggle("ha-off", !chosen);
        }

        function press(key) {
            if (replaceOnType && key !== "back") typed = "";
            replaceOnType = false;
            if (key === "back") typed = typed.slice(0, -1);
            else if (key === ".") {
                if (typed && !/\.$/.test(typed) && (typed.match(/\./g) || []).length < 2) typed += ".";
            } else if (typed.length < 10) typed += key;
            update();
        }

        /** Schnelltaste: Datum uebernehmen und als TT.MM.JJJJ anzeigen */
        function setChosen(isoDate) {
            var p = isoDate.split("-");
            typed = p[2] + "." + p[1] + "." + p[0];
            replaceOnType = true;
            update();
        }

        function submit(date) {
            if (busy) return;
            busy = true;
            error.textContent = "";
            request("POST", "bestbefore", {id: item.id, date: date}).then(function () {
                busy = false;
                closeDialog();
                refresh();
            }).catch(function (e) {
                busy = false;
                error.textContent = t("error") + ": " + e.message;
            });
        }

        function save() {
            if (chosen) submit(chosen);
            else error.textContent = t("invalid");
        }

        // Ziffernblock auf dem Bildschirm
        var keys = el("div", {"class": "ha-keys"});
        ["1", "2", "3", "4", "5", "6", "7", "8", "9", ".", "0", "back"].forEach(function (key) {
            var attrs = {
                "class": key === "." || key === "back" ? "ha-key-fn" : "",
                "aria-label": key === "back" ? t("backspace") : key,
                onclick: function () {
                    press(key);
                }
            };
            if (key !== "back") attrs.text = key;
            keys.appendChild(el("button", attrs, key === "back" ? [icon("backspace")] : []));
        });

        // Angeschlossene Tastatur funktioniert ebenfalls
        keyHandler = function (event) {
            if (event.ctrlKey || event.altKey || event.metaKey) return;
            if (/^[0-9]$/.test(event.key)) press(event.key);
            else if ([".", ",", "/", "-"].indexOf(event.key) >= 0) press(".");
            else if (event.key === "Backspace") press("back");
            else if (event.key === "Escape") { typed = ""; update(); }
            else if (event.key === "Enter") save();
            else return;
            event.preventDefault();
        };
        document.addEventListener("keydown", keyHandler);

        var quick = [
            [3, "days"], [7, "week"], [14, "weeks"]
        ].map(function (q) {
            var label = "+" + (q[0] === 7 ? 1 : q[0] === 14 ? 2 : q[0]) + " " + t(q[1]);
            return el("button", {text: label, onclick: function () {
                setChosen(addDays(q[0]));
            }});
        }).concat([[1, "month"], [3, "months"], [12, "year"]].map(function (q) {
            var label = "+" + (q[0] === 12 ? 1 : q[0]) + " " + t(q[1]);
            return el("button", {text: label, onclick: function () {
                setChosen(addMonths(q[0]));
            }});
        }));
        if (state.last_date) {
            quick.push(el("button", {"class": "ha-wide", text: t("asLast") + " " + formatDate(state.last_date), onclick: function () {
                setChosen(state.last_date);
            }}));
        }

        saveButton = el("button", {"class": "ha-save ha-off", text: t("save"), onclick: save});

        var amount = item.amount && item.amount !== 1 ? " · " + t("amount") + ": " + item.amount : "";
        var box = el("div", {"class": "ha-box ha-bb"}, [
            el("h2", {text: t("bbTitle")}),
            item.has_picture ? el("img", {"class": "ha-pic", alt: "", src: API + "?action=picture&product=" + item.product_id}) : null,
            el("div", {"class": "ha-name", text: item.name}),
            el("div", {"class": "ha-sub", text: (state.session ? locationLabel(state.session) : "") + amount}),
            el("button", {"class": "ha-edit-link", text: t("editProduct"), onclick: function () {
                window.haStorageEditProduct(item.product_id);
            }}),
            el("div", {"class": "ha-entry"}, [
                el("div", {}, [display, preview, keys]),
                el("div", {}, [
                    el("div", {"class": "ha-quick-title", text: t("quickTitle")}),
                    el("div", {"class": "ha-quick"}, quick)
                ])
            ]),
            el("div", {"class": "ha-actions"}, [
                saveButton,
                el("button", {"class": "ha-never", text: t("never"), onclick: function () {
                    submit("never");
                }}),
                el("button", {
                    "class": "ha-skip",
                    text: t("skip") + (item.best_before_date ? " (" + formatDate(item.best_before_date) + ")" : " (" + t("skipHint") + ")"),
                    onclick: function () {
                        submit(null);
                    }
                })
            ]),
            error,
            el("div", {"class": "ha-busy" + (scanBusy ? " ha-on" : "")}, [el("span", {"class": "ha-spin"}), el("span", {text: t("busyScan")})]),
            el("div", {"class": "ha-more", text: state.pending.length > 1 ? (state.pending.length - 1) + " " + t("more") : ""})
        ]);

        dialog = el("div", {"class": "ha-modal"}, [box]);
        document.body.appendChild(dialog);
        update();
    }

    // Nur fuer Tests: Datumserkennung von aussen pruefbar
    window.haStorageParseDate = parseDate;

    // ------------------------------------------------------------------ Abgleich

    // ------------------------------------------------------------------ Rueckmeldung waehrend der Verarbeitung
    // Die Screen-Seite meldet "Scan wird verarbeitet" und "Ergebnis da". Danach wird ein paar Sekunden
    // schnell nachgefragt, weil die MHD-Abfrage erst entsteht, wenn Grocy den Einkauf gebucht hat.
    var scanBusy = false, busyTimer = null, fastUntil = 0, fastTimer = null, lastPendingCount = 0;

    function setScanBusy(on) {
        scanBusy = on;
        clearTimeout(busyTimer);
        if (on) busyTimer = setTimeout(function () { setScanBusy(false); }, 45000);
        var line = dialog && dialog.querySelector(".ha-busy");
        if (line) line.classList.toggle("ha-on", on);
    }

    function fastPoll() {
        clearTimeout(fastTimer);
        if (Date.now() >= fastUntil) {
            setScanBusy(false);
            return;
        }
        fastTimer = setTimeout(function () {
            refresh().then(fastPoll);
        }, 350);
    }

    window.haStorageActive = function () {
        return !!(state && state.session);
    };
    window.haStorageProgress = function () {
        if (state && state.session) setScanBusy(true);
    };
    window.haStorageAfterScan = function () {
        if (!state || !state.session) return;
        fastUntil = Date.now() + 6000;
        fastPoll();
    };

    function refresh() {
        return request("GET", "state").then(function (data) {
            state = data;
            if (data.pending.length > lastPendingCount) {
                // Neue MHD-Abfrage ist da: schnelles Nachfragen und Hinweis beenden
                fastUntil = 0;
                setScanBusy(false);
            }
            lastPendingCount = data.pending.length;
            if (data.lang && T[data.lang]) lang = data.lang;
            else if (data.lang) lang = "en";
            renderBanner();
            var next = data.session && data.pending.length ? data.pending[0] : null;
            if (!next) {
                closeDialog();
            } else if (next.id !== shownId && !busy) {
                showDialog(next);
            } else if (dialog) {
                var more = dialog.querySelector(".ha-more");
                if (more) more.textContent = data.pending.length > 1 ? (data.pending.length - 1) + " " + t("more") : "";
            }
        }).catch(function () {
            // Verbindungsfehler: beim naechsten Durchlauf erneut versuchen
        });
    }

    function init() {
        var style = document.createElement("style");
        style.textContent = css;
        document.head.appendChild(style);
        // Seiten koennen einen Platz fuer den Balken vorgeben, sonst wird er oben fixiert
        banner = document.getElementById("ha-banner");
        if (!banner) {
            banner = el("div", {id: "ha-banner", "class": "ha-fixed"});
            document.body.appendChild(banner);
        }

        var entry = document.getElementById("ha-storage-menu");
        refresh().then(function () {
            if (entry && !entry.children.length) entry.textContent = t("menu");
        });
        setInterval(function () {
            if (!document.hidden) refresh();
        }, POLL_MS);
    }

    window.haStorageOpen = function () {
        openPicker();
        return false;
    };

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
})();
