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
            pickTitle: "Wo wird eingelagert?",
            storing: "Einlagern in",
            stop: "Beenden",
            open: "offen",
            bbTitle: "Mindestens haltbar bis",
            placeholder: "z.B. 151226 oder 12/26",
            hint: "TTMMJJ, TT.MM.JJJJ oder MM/JJ (= Monatsende)",
            save: "Speichern",
            never: "Läuft nicht ab",
            skip: "Überspringen",
            skipHint: "Standard behalten",
            asLast: "Wie zuletzt",
            calendar: "Kalender",
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
            pickTitle: "Where are you storing items?",
            storing: "Storing in",
            stop: "Stop",
            open: "open",
            bbTitle: "Best before",
            placeholder: "e.g. 151226 or 12/26",
            hint: "DDMMYY, DD.MM.YYYY or MM/YY (= end of month)",
            save: "Save",
            never: "Never expires",
            skip: "Skip",
            skipHint: "keep default",
            asLast: "Same as last",
            calendar: "Calendar",
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

    function request(method, action, body) {
        var options = {method: method, credentials: "same-origin", headers: {}};
        if (body !== undefined) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        }
        return fetch(API + "?action=" + encodeURIComponent(action), options).then(function (response) {
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
        var date = new Date(y, m - 1, d);
        return date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d ? iso(date) : null;
    }

    function endOfMonth(y, m) {
        if (m < 1 || m > 12) return null;
        return iso(new Date(y, m, 0));
    }

    function fullYear(y) {
        return y < 100 ? 2000 + y : y;
    }

    /** Erkennt TTMMJJ, TTMMJJJJ, MMJJ, MMJJJJ, TT.MM.JJ(JJ), MM/JJ(JJ) */
    function parseDate(input) {
        var s = (input || "").trim();
        if (!s) return null;
        var parts = s.split(/[.\/\-\s]+/).filter(Boolean);
        if (parts.length === 3) {
            return validDate(fullYear(+parts[2]), +parts[1], +parts[0]);
        }
        if (parts.length === 2) {
            return endOfMonth(fullYear(+parts[1]), +parts[0]);
        }
        var digits = s.replace(/\D/g, "");
        if (digits.length === 8) return validDate(+digits.substr(4, 4), +digits.substr(2, 2), +digits.substr(0, 2));
        if (digits.length === 6) {
            return validDate(fullYear(+digits.substr(4, 2)), +digits.substr(2, 2), +digits.substr(0, 2))
                || endOfMonth(+digits.substr(2, 4), +digits.substr(0, 2));
        }
        if (digits.length === 4) return endOfMonth(fullYear(+digits.substr(2, 2)), +digits.substr(0, 2));
        return null;
    }

    // ------------------------------------------------------------------ Styles

    var css = "" +
        "#ha-banner{position:fixed;top:0;left:0;right:0;z-index:5;background:#2e7d32;color:#fff;font:bold 1.4em arial;" +
        "padding:10px 16px;display:none;align-items:center;justify-content:space-between;gap:12px}" +
        "#ha-banner button{font:bold 0.9em arial;padding:8px 16px;border:0;border-radius:4px;background:#fff;color:#2e7d32}" +
        ".ha-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:20;background:rgba(0,0,0,.85);display:flex;align-items:center;" +
        "justify-content:center;padding:16px;box-sizing:border-box}" +
        ".ha-box{background:#fff;border-radius:8px;padding:20px;width:100%;max-width:640px;max-height:100%;overflow:auto;" +
        "box-sizing:border-box;font-family:arial;text-align:center}" +
        ".ha-box h2{margin:0 0 6px;font-size:1.3em;color:#555}" +
        ".ha-name{font-size:1.9em;font-weight:bold;margin:4px 0}" +
        ".ha-sub{color:#666;margin-bottom:10px}" +
        ".ha-pic{max-width:160px;max-height:160px;margin:6px auto;display:block}" +
        ".ha-input{font-size:2em;width:100%;box-sizing:border-box;padding:10px;text-align:center;border:2px solid #31B0D5;border-radius:6px}" +
        ".ha-preview{font-size:1.3em;min-height:1.6em;margin:6px 0;color:#2e7d32;font-weight:bold}" +
        ".ha-preview.ha-bad{color:#c62828}" +
        ".ha-hint{color:#888;font-size:.9em;margin-bottom:10px}" +
        ".ha-grid{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin:10px 0}" +
        ".ha-grid button,.ha-actions button,.ha-loc{font:bold 1.1em arial;padding:14px 16px;border:0;border-radius:6px;" +
        "background:#e3f2fd;color:#0d47a1;flex:1 1 28%;min-width:120px}" +
        ".ha-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}" +
        ".ha-actions .ha-save{background:#2e7d32;color:#fff;flex:1 1 100%;font-size:1.4em}" +
        ".ha-actions .ha-never{background:#fff3e0;color:#e65100}" +
        ".ha-actions .ha-skip{background:#eee;color:#333}" +
        ".ha-loc{display:block;width:100%;margin:8px 0;font-size:1.5em}" +
        ".ha-cal{font-size:1.1em;padding:6px;margin-top:4px}" +
        ".ha-more{margin-top:10px;color:#666}" +
        ".ha-error{color:#c62828;margin-top:8px;min-height:1.2em}";

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
        banner.appendChild(el("span", {text: "📦 " + t("storing") + ": " + session.location_name + open}));
        banner.appendChild(el("button", {text: t("stop"), onclick: stopSession}));
        banner.style.display = "flex";
        document.body.style.paddingTop = banner.offsetHeight + "px";
    }

    function stopSession() {
        request("POST", "stop", {}).then(refresh).catch(showGlobalError);
    }

    function showGlobalError(error) {
        alert(t("error") + ": " + error.message);
    }

    // ------------------------------------------------------------------ Lagerort-Auswahl

    function openPicker() {
        if (typeof window.closeNav === "function") window.closeNav();
        request("GET", "locations").then(function (locations) {
            var modal = el("div", {"class": "ha-modal"});
            var box = el("div", {"class": "ha-box"}, [el("h2", {text: t("pickTitle")})]);
            if (!locations.length) box.appendChild(el("p", {text: t("noLocations")}));
            locations.forEach(function (location) {
                box.appendChild(el("button", {
                    "class": "ha-loc", text: location.name, onclick: function () {
                        request("POST", "start", {location_id: location.id}).then(function () {
                            modal.remove();
                            refresh();
                        }).catch(showGlobalError);
                    }
                }));
            });
            box.appendChild(el("div", {"class": "ha-actions"}, [
                el("button", {"class": "ha-skip", text: t("cancel"), onclick: function () {
                    modal.remove();
                }})
            ]));
            modal.appendChild(box);
            document.body.appendChild(modal);
        }).catch(showGlobalError);
    }

    // ------------------------------------------------------------------ MHD-Dialog

    var dialog = null;

    function closeDialog() {
        if (dialog) dialog.remove();
        dialog = null;
        shownId = null;
    }

    function showDialog(item) {
        closeDialog();
        shownId = item.id;
        var chosen = null;

        var input = el("input", {
            "class": "ha-input", type: "text", inputmode: "numeric", autocomplete: "off",
            placeholder: t("placeholder")
        });
        var preview = el("div", {"class": "ha-preview"});
        var error = el("div", {"class": "ha-error"});
        var calendar = el("input", {"class": "ha-cal", type: "date"});

        function setChosen(isoDate, fromInput) {
            chosen = isoDate;
            preview.classList.toggle("ha-bad", !isoDate && !!input.value);
            preview.textContent = isoDate ? "→ " + formatDate(isoDate) : (input.value ? t("invalid") : "");
            if (!fromInput) input.value = isoDate && isoDate !== "2999-12-31" ? formatDate(isoDate) : "";
            if (isoDate && isoDate !== "2999-12-31") calendar.value = isoDate;
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

        input.addEventListener("input", function () {
            setChosen(parseDate(input.value), true);
        });
        input.addEventListener("keydown", function (event) {
            if (event.key === "Enter" && chosen) submit(chosen);
        });
        calendar.addEventListener("change", function () {
            if (calendar.value) setChosen(calendar.value, false);
        });

        var quick = [
            [3, "days"], [7, "week"], [14, "weeks"]
        ].map(function (q) {
            var label = "+" + (q[0] === 7 ? 1 : q[0] === 14 ? 2 : q[0]) + " " + t(q[1]);
            return el("button", {text: label, onclick: function () {
                setChosen(addDays(q[0]), false);
            }});
        }).concat([[1, "month"], [3, "months"], [12, "year"]].map(function (q) {
            var label = "+" + (q[0] === 12 ? 1 : q[0]) + " " + t(q[1]);
            return el("button", {text: label, onclick: function () {
                setChosen(addMonths(q[0]), false);
            }});
        }));
        if (state.last_date) {
            quick.push(el("button", {text: t("asLast") + " " + formatDate(state.last_date), onclick: function () {
                setChosen(state.last_date, false);
            }}));
        }

        var amount = item.amount && item.amount !== 1 ? " · " + t("amount") + ": " + item.amount : "";
        var box = el("div", {"class": "ha-box"}, [
            el("h2", {text: t("bbTitle")}),
            item.has_picture ? el("img", {"class": "ha-pic", alt: "", src: API + "?action=picture&product=" + item.product_id}) : null,
            el("div", {"class": "ha-name", text: item.name}),
            el("div", {"class": "ha-sub", text: (state.session ? state.session.location_name : "") + amount}),
            input,
            preview,
            el("div", {"class": "ha-hint", text: t("hint")}),
            el("div", {"class": "ha-grid"}, quick),
            el("div", {}, [el("label", {text: t("calendar") + ": "}), calendar]),
            el("div", {"class": "ha-actions"}, [
                el("button", {"class": "ha-save", text: t("save"), onclick: function () {
                    if (chosen) submit(chosen);
                    else {
                        error.textContent = t("invalid");
                        input.focus();
                    }
                }}),
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
            state.pending.length > 1 ? el("div", {"class": "ha-more", text: (state.pending.length - 1) + " " + t("more")}) : null
        ]);

        dialog = el("div", {"class": "ha-modal"}, [box]);
        document.body.appendChild(dialog);
        if (item.best_before_date && item.best_before_date !== "2999-12-31") calendar.value = item.best_before_date;
        input.focus();
    }

    // ------------------------------------------------------------------ Abgleich

    function refresh() {
        return request("GET", "state").then(function (data) {
            state = data;
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
                if (more) more.textContent = (data.pending.length - 1) + " " + t("more");
            }
        }).catch(function () {
            // Verbindungsfehler: beim naechsten Durchlauf erneut versuchen
        });
    }

    function init() {
        var style = document.createElement("style");
        style.textContent = css;
        document.head.appendChild(style);
        banner = el("div", {id: "ha-banner"});
        document.body.appendChild(banner);

        var entry = document.getElementById("ha-storage-menu");
        refresh().then(function () {
            if (entry) entry.textContent = t("menu");
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
