# Changelog

## 2.2.14
- Supporto a `{cfyoutube ID}` e ai contenitori YOOtheme `.cf-youtube[data-video-id]`, con placeholder locale 16:9 e osservazione del contenuto dinamico.
- Cache amministrativa delle miniature YouTube in `images/youtube/` (WebP con fallback JPEG); endpoint pubblico locale senza download remoto.

## 2.2.13
- Registro persistente e deduplicato dei cookie realmente osservati tramite header HTTP, senza memorizzare valori o token; include dominio, path, durata, attributi, fonte e momento dell'osservazione.
- Scheda Cookie rilevati trasformata in registro osservazionale, con export CSV. Una scansione browser assente resta esplicitamente non eseguita: nessun risultato è simulato.
- L'avviso reCAPTCHA è inserito esclusivamente dentro form Convert Forms identificati con certezza; eliminato il fallback visibile in fondo alla pagina.

## 2.2.12 — 2026-11-06

- Avviso reCAPTCHA compatto (`.jck-consent-hint`) al posto del placeholder YouTube 16:9, così «Rifiuta tutti» non distorce i moduli Convert Forms.
- Lo script reCAPTCHA resta bloccato prima del consenso; l’avviso non sostituisce il markup del form.
- Spinner sul pulsante «Avvia scansione» e blocco dei doppi avvii.
- Un solo selettore «Metodo di scoperta» nel form di avvio.
- Sitemap lette da `robots.txt`; `/sitemap.xml` solo come fallback; HTTP 404 non conta come pagina analizzata.

## 2.2.11 — 2026-11-06

- Catalogo servizi popolato dalla scansione (ID stabile, niente duplicati) e dalle regole incorporate, senza sovrascrivere classificazioni manuali o approvate.
- I servizi sconosciuti restano da verificare e non sono trattati come necessari né bloccati automaticamente.
- Panoramica, Servizi e Diagnostica mostrano classificazione proposta/approvata, evidenza, pagine, regola e se il blocco è stato visto in scansione HTTP.
- Cookie: distinzione Set-Cookie / riferimento nel codice / browser (quest’ultimo solo se la scansione browser è realmente disponibile).
- reCAPTCHA e Convert Forms restano in Contenuti esterni, con avviso per autorizzare i moduli; YouTube `<video src=".../embed/">` segnalato senza riscrittura.
- Schema `#__joomcookies_services` esteso in modo versionato (2.2.11.sql).

## 2.2.10 — 2026-11-06

- Riconoscimento/blocco di Google reCAPTCHA e Convert Forms nella categoria contenuti esterni.
- Script autorizzati riattivati in ordine, con attesa delle dipendenze esterne e compatibilità callable `$load` YOOtheme.
- Blocco dei pulsanti di caricamento degli iframe generici prima del consenso.
- Scanner e motore di blocco allineati sulla classificazione reCAPTCHA.
- Restano da verificare in browser attivazione GA, invio Convert Forms e copertura generale.


## 2.2.9 — 2026-11-06

- URL non HTTP (`viber:`, `tel:`, `mailto:`, …) esclusi prima della normalizzazione; non diventano percorsi del sito.
- Cookie di sessione Joomla suggerito solo con attributi Set-Cookie (HttpOnly, first-party, sessione), non dal solo nome esadecimale.
- reCAPTCHA non è più classificato automaticamente come necessario; Google Fonts e Font Awesome sono riconosciuti come non classificati.
- Lo stub Consent Mode non viene segnalato come Analytics; scansioni `running` abbandonate vengono messe in pausa.

## 2.2.8 — 2026-11-06

- Corretto il parse error PHP nella pagina Scansione (`<?php` duplicato nel template).
- La scoperta sitemap resta autonoma: solo `robots.txt` e sitemap XML dello stesso host, senza JL Sitemap né altre estensioni.

## 2.2.7 — 2026-11-06

- Registro persistente **URL individuati** (originale, normalizzato, provenienza, metodo, HTTP, redirect, esito, motivo, canonical) con ricerca, filtri, paginazione ed export CSV.
- Deduplica: frammenti, slash finale, parametri di tracciamento, redirect e canonical; niente seed `index.php?Itemid=`.
- Metodi di scoperta: crawling HTML (predefinito), sitemap XML (anche index) e combinato. Nessun tetto sulle 75 URL della sitemap.
- Riconoscimento GA4/GTM/YOOtheme, reCAPTCHA e YouTube anche da `data-jck-src` / placeholder; distingue codice, blocco preventivo, Set-Cookie e cookie browser (questi ultimi non simulati).
- Completamento per limite di pagine distinto da coda vuota; pause/resume/cancel invariati.

## 2.2.6 — 2026-11-06

- Motore di scansione: i lotti successivi partono da soli (`scan.batch` in loop, watchdog HTML se il fetch non è disponibile). Non serve più premere Avvia.
- Pausa, Riprendi e Annulla sono form POST (funzionano anche senza JavaScript) e hanno feedback sullo stato reale.
- Limite della scansione rapida applicato sul conteggio live delle pagine `done`; coda più grande del tetto ammessa ma non analizzata oltre il cap.
- Tempo attivo persistito; lotti `running` bloccati recuperati; JSON con `Cache-Control` / `X-LiteSpeed-Cache-Control: no-cache`.
- Corretto l’import di `HttpCrawler` in `ScanController`.

## 2.2.5 — 2026-11-06

- Scanner: `scan.start` esegue il primo lotto (non si limita a creare il job) e una scansione `running` riprende invece di restare ferma a zero.
- I lotti successivi restano su `scan.batch` (AJAX); errori visibili all’amministratore; dopo un reload la scansione in corso continua.
- Il tetto di 25 secondi resta per ogni richiesta PHP, non per l’intera scansione completa.

## 2.2.4 — 2026-11-06

- Blocco preventivo di Google Analytics 4 e del loader YOOtheme `theme-analytics/app/analytics.min.js`, senza copiare il Measurement ID e senza modificare YOOtheme.
- Prima del consenso Statistiche non parte `gtag.js` né `page_view`; dopo l’accettazione si esegue lo script originale una sola volta (coda `$load` YOOtheme adattata se già consumata).
- Consent Mode v2: default `denied` cache-safe in head e `update` dopo la scelta; la rete di sicurezza non sostituisce il blocco preventivo.
- Revoca Statistiche: stop degli eventi successivi, cancellazione `_ga` / `_ga_*` / `_gid` visibili al sito; reCAPTCHA e Maps non sono bloccati.

## 2.2.3 — 2026-11-06

- Dopo «Accetta tutti» o l'autorizzazione di Contenuti esterni i segnaposto YouTube diventano iframe, anche al ricaricamento e con HTML in cache.
- Gli iframe usano `autoplay=0`; in revoca i player vengono rimossi e i segnaposto ripristinati.
- Diagnostica opzionale: versione asset, stato consenso, iframe bloccati/sbloccati (nessun dato personale).

## 2.2.2 — 2026-11-06

- HTML pubblico sempre in forma bloccata: il consenso si applica nel browser, compatibile con LiteSpeed Cache senza vary sul cookie.
- Asset CSS/JS versionati (`?v=2.2.2`) e marcati per escludere defer, delay, minify e combine.
- Dopo consenso o revoca i placeholder YouTube si aggiornano in pagina, anche se l'HTML arriva dalla cache.

## 2.2.1 — 2026-11-06

- Corretto il blocco del frontend: il MutationObserver YouTube non riscrive più tutto il DOM a ogni mutazione.
- I placeholder YouTube PHP avvolgono soltanto i contenitori `.youtube-player` vuoti, senza spezzare markup nidificato YOOtheme.
- Errori JavaScript di inizializzazione non interrompono il resto della pagina.

## 2.2.0 — 2026-11-06

- Segnaposto YouTube 16:9 responsive per griglie e video singoli, con variante compatta e pulsanti JoomCookies.
- Supporto ai contenitori esistenti `.youtube-player[data-id]`, anche se aggiunti dinamicamente.
- YouTube non viene auto-iniettato all'accettazione generale: serve un clic esplicito su "Carica il video" dopo il consenso.
- Scansione rapida (limite configurabile, default 50) e scansione completa senza tetto arbitrario di pagine.
- Coda persistente a lotti con pausa, ripresa, annullamento e risultati raggruppati.
- Scansione browser Playwright/Chromium ancora solo come architettura documentata, non operativa.

## 2.1.1 — 2026-11-06

- Banner isolato, centrato e con z-index massimo; titolo e testi usano i colori configurati.
- La pagina resta scorrevole con il banner aperto: `overflow: hidden` solo sul pannello Personalizza.
- Root montato su `document.body` per evitare stacking context del template.

## 2.1.0 — 2026-11-06

- Banner e JavaScript iniettati anche in `onAfterRender` se YOOtheme o un ottimizzatore omettono gli asset in head.
- API pubblica `window.JoomCookies` (`hasConsent`, `openPreferences`, eventi `joomcookies:ready` e `joomcookies:change`).
- Testi italiani precompilati per nuove installazioni, senza sovrascrivere i testi già salvati.
- Interfaccia amministrativa localizzata (it-IT/en-GB), senza chiavi tecniche visibili né riquadro migrazione V1.
- Riepilogo scansione con pagine, cookie osservati, script, iframe e servizi identificati.

## 2.0.2 — 2026-11-06

- Corretto il frontend: il plugin importa `ComponentConfigBridge` dal namespace `Service`. Senza l'import Joomla cercava la classe in `Extension` e interrompeva la pagina.

## 2.0.1 — 2026-11-06

- Corretto l'errore SQL `Duplicate entry 'necessary' for key 'idx_alias'` in reinstallazione/aggiornamento: il seed delle categorie è idempotente e non è più nell'install SQL.

## 2.0.0 — 2026-11-06

- Componente amministrativo `com_joomcookies` con panoramica, scansione, cookie rilevati, servizi, categorie, banner, testi, YouTube e diagnostica.
- Motore di scansione HTTP con limiti, sitemap, link interni, header Set-Cookie, riferimenti in sorgente e confronto tra scansioni.
- Protezioni SSRF, ACL e CSRF sulle operazioni di scrittura e scansione.
- Migrazione idempotente e reversibile dei parametri del plugin V1 verso il componente.
- Le regole di blocco extra dalla scansione restano disattive finché non sono approvate e abilitate.
- Contratto per scansione Playwright/Chromium dichiarato non operativo (nessun risultato simulato).
- Package unico `pkg_joomcookies` 2.0.0.

## 1.0.0 — 2026-11-06

- Prima versione pubblica del package `pkg_joomcookies`.
- Plugin di sistema con banner accessibile, quattro categorie di consenso e cookie tecnico di prima parte.
- Blocco preventivo di script e iframe gestiti (catalogo incorporato + registro amministrativo).
- Segnaposto YouTube centralizzato, shortcode `{joomcookies youtube="..."}` e supporto a `youtube-nocookie.com`.
- Interfaccia di configurazione nel plugin Joomla, lingue italiano/inglese, script di installazione e aggiornamento.
- Modalità diagnostica, pulsante permanente e shortcode `{joomcookies-preferences}`.
