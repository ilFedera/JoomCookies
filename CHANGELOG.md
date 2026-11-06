# Changelog

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
