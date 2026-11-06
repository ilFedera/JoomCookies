# Changelog

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
