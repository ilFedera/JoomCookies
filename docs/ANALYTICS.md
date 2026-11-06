# Google Analytics, YOOtheme e consenso (2.2.4)

## Scelta tecnica: blocco preventivo + Consent Mode v2 come rete di sicurezza

JoomCookies **non** si affida al solo Google Consent Mode v2 per impedire il tracciamento.

Consent Mode, da solo, carica comunque `gtag.js` e può inviare ping (anche cookieless) verso Google prima di una scelta. Per GDPR/ePrivacy il requisito di questo package è: **nessun evento Analytics, compreso `page_view`, finché la categoria Statistiche non è accettata**.

Per questo:

1. **Blocco preventivo (primario).** HtmlGuard riscrive in `type="text/plain"`:
   - `googletagmanager.com` / `gtag/js` / `google-analytics.com`
   - lo script locale YOOtheme `theme-analytics/app/analytics.min.js`
   - snippet inline con `gtag(` o URL `gtag/js`
2. **Consenso nel browser.** Solo con Statistiche lo script originale viene eseguito **una volta** (`data-jck-loaded` + chiave anti-duplicato). Il Measurement ID resta quello di YOOtheme (o di altro template): **non va copiato** in JoomCookies.
3. **Consent Mode v2 (secondario).** Un snippet cache-safe in `<head>` imposta sempre `analytics_storage` / `ad_*` su `denied`. Dopo la scelta, `joomcookies.js` invia `gtag('consent','update', …)` **prima** di attivare gli script. L’HTML del default denied è identico per tutti i visitatori (LiteSpeed senza vary).
4. **Revoca.** Update Consent Mode a `denied`, cancellazione dei cookie `_ga`, `_ga_*`, `_gid`, `_gat`, `_gcl_*` visibili al sito, reload predefinito. La pagina in cache resta quella bloccata: al ricaricamento `gtag.js` non parte.

Non si intercetta `appendChild` / `Function` in modo globale. L’unico adattamento YOOtheme è un proxy di `window.$load` **se è già una funzione** (coda già eseguita), così `analytics.min.js` può ancora registrare il proprio callback senza duplicare `gtag('config')` a mano.

Non si bloccano reCAPTCHA, Maps o altri host Google fuori dal catalogo Analytics/GTM/Ads.

## YOOtheme Pro

`theme-analytics/app/analytics.min.js` non contiene il Measurement ID: lo riceve da `$load` e poi inietta `https://www.googletagmanager.com/gtag/js?id=…`. Se quello script locale restasse eseguibile, `page_view` partirebbe prima del banner. JoomCookies **non modifica i file YOOtheme**: li riconosce dall’URL.

Altri template Joomla restano coperti dal catalogo `gtag` / GTM / GA.

## Cosa non è verificato in questo ambiente

Nessun Joomla+YOOtheme 4.5.21+LiteSpeed in locale. La prova reale (Network: nessun `collect` / `gtag/js` senza consenso; un solo `config` dopo l’accettazione) va fatta sul sito di test dopo l’installazione del ZIP.
