# Documentazione tecnica JoomCookies 2.2.9

## Architettura

Il package Joomla `pkg_joomcookies` installa:

- il componente `com_joomcookies` (configurazione, scansioni, amministrazione)
- il plugin di sistema `plg_system_joomcookies` (banner, consenso, blocco preventivo)

La fonte principale delle impostazioni è il componente. I parametri del plugin restano un fallback.

Eventi sottoscritti tramite `SubscriberInterface` (modello Joomla 5/6):

| Evento | Ruolo |
|--------|--------|
| `onContentPrepare` | Shortcode YouTube e pulsante preferenze negli articoli/moduli preparati |
| `onAfterDispatch` / `onBeforeCompileHead` (HIGH) | WebAssetManager: CSS/JS locali, variabili colore |
| `onAfterRender` (MIN) | Riscrittura HTML, iniezione banner, JSON, CSS/JS di riserva |

Il plugin gira solo nel client `site` e solo su documenti HTML. Errori interni sono assorbiti per non interrompere il sito.

## Consenso

Payload (JSON, poi Base64URL) nel cookie di prima parte `joomcookies_consent` e, se disponibile, in `localStorage`:

```json
{"v":"1","t":1730000000,"c":{"necessary":true,"statistics":false,"marketing":false,"embeds":false}}
```

- `v`: versione informativa impostata in amministrazione
- `t`: Unix timestamp della scelta
- `c`: categorie

Se `v` non coincide con la configurazione corrente, il banner viene riproposto. Non è un cookie di profilazione.

## Blocco preventivo

`HtmlGuard` analizza l'HTML finale:

1. `<script src>` il cui host o URL corrisponde al registro → `type="text/plain"` e `data-jck-src`
2. script inline che contengono firme note (`gtag(`, `fbq(`, URL GTM) → stesso trattamento
3. iframe YouTube/Vimeo/Maps e altri match → segnaposto locale
4. shortcode `{joomcookies youtube=...}` → segnaposto o player

Dopo il consenso il JavaScript locale clona gli script in elementi eseguibili una sola volta (`data-jck-loaded` + chiave anti-duplicato). La revoca di una categoria già accettata ricarica la pagina e tenta di cancellare i cookie di prima parte elencati nel servizio (per Analytics anche `_ga_*`). I cookie HttpOnly o di terzi non sono cancellabili dal browser del sito.

Asset Joomla in `/media/system/` e `/media/vendor/` non vengono toccati. `/media/templates/` **non** è in whitelist globale, così `theme-analytics` di YOOtheme può essere bloccato; gli altri JS di template restano eseguibili se non sono nel catalogo. Gli script del plugin hanno `data-jck-ignore` implicito tramite path/id.

Google Analytics: vedi `docs/ANALYTICS.md` (blocco preventivo YOOtheme/gtag, Consent Mode v2 come rete di sicurezza, nessun Measurement ID da copiare).

## Registro servizi

Il catalogo incorporato copre, tra gli altri: Google Tag Manager, Google Analytics, gtag.js, loader YOOtheme `theme-analytics`, gtag inline, Hotjar, Meta Pixel, DoubleClick, Ads, AdSense, YouTube, youtube-nocookie, Vimeo, Google Maps, Instagram, TikTok, Google reCAPTCHA. Lo scanner rileva reCAPTCHA; il plugin ora blocca preventivamente i JS riconosciuti di Google reCAPTCHA e l'adapter di Convert Forms nella categoria `embeds`. Il modulo può richiedere il consenso ai contenuti esterni prima dell'invio: verificare accessibilità e invio reale, senza presumere che reCAPTCHA sia sempre strettamente necessario.

L'amministratore può aggiungere regole:

- dominio
- URL contiene
- id dello script
- contenuto inline contiene

GTM può caricare tag di marketing dallo stesso contenitore. Se il contenitore include ads, classificare GTM come marketing oppure spezzare i tag.

## YouTube

Sintassi unica:

```text
{joomcookies youtube="ID" title="..." ratio="16:9"}
```

Prima del consenso: nessun iframe, nessuna richiesta a `youtube.com` / `youtube-nocookie.com`. Miniatura da `i.ytimg.com` solo se l'opzione è abilitata esplicitamente.

Dopo «Accetta tutti» o l'autorizzazione di Contenuti esterni: i segnaposto diventano iframe verso `youtube-nocookie.com` (predefinito) **senza autoplay**. Il HTML pubblico resta bloccato (cache-safe); lo sblocco avviene in JavaScript.

API pubblica:

```javascript
window.JoomCookies.hasConsent('embeds');
window.JoomCookies.openPreferences();
document.addEventListener('joomcookies:change', handler);
document.addEventListener('joomcookies:ready', handler);
```

L'HTML pubblico è sempre in forma bloccata (adatto a LiteSpeed senza vary sul cookie). Il consenso si applica in JavaScript. Vedi `docs/LITESPEED.md`. Gli asset CSS/JS usano `?v=` con la versione del package e attributi `data-no-optimize` / `data-no-defer` / `data-no-delay`.

Lightbox e griglie: se inizializzano gli iframe al `DOMContentLoaded`, dopo l'accettazione della categoria "contenuti esterni" può essere necessario un ricaricamento.

## Limiti del blocco (non universale)

JoomCookies **non** può garantire il blocco di:

- script eseguiti prima di `onAfterRender` (output anticipato, server-side include)
- richieste avviate da altre estensioni in PHP (pixel server-side, API server-to-server)
- cookie impostati da risposta HTTP di terzi o da PHP
- codice in template/override non riconoscibile dal registro
- Web Workers, pixel `<img>`, `link rel=preconnect` non censiti
- appunti già eseguiti in una pagina precedente alla revoca, fino al reload

Questi casi richiedono integrazione specifica, blocco lato server o rimozione dello script alla fonte.

## Aggiornamenti

1. Installare il nuovo ZIP del package (upgrade).
2. Se cambia l'informativa o il perimetro dei servizi, incrementare `consent_version`.
3. Per un update server proprio, copiare `docs/updates/joomcookies-updates.xml.example` su un URL HTTPS e aggiungere `<updateservers>` nei manifest.

Non è incluso un URL di update pubblico in questa versione, per evitare dipendenze da host esterni.

## Scanner (PHP)

Lo scanner HTTP non esegue JavaScript e non avvia un browser. Vede l’HTML consegnato da PHP (incluso il markup già bloccato da JoomCookies) e gli header `Set-Cookie` della risposta del sito. Non osserva cookie impostati dopo il consenso, tag iniettati da GTM a runtime, né iframe YouTube che esistono solo come placeholder.

Metodi di scoperta:

- **html** (predefinito): URL di partenza, menu pubblici in forma SEF, collegamenti `<a>` interni
- **sitemap**: `robots.txt`, `/sitemap.xml` e `/sitemap_index.xml` dello stesso host (protocollo XML standard, anche gli index). Non usa JL Sitemap né altre estensioni; non segue i link HTML
- **combined**: sitemap più crawling HTML

Ogni URL considerato è scritto in `#__joomcookies_scan_urls` e resta consultabile dopo il completamento. La scansione rapida può terminare per limite di pagine con coda ancora piena; quella completa termina quando la coda è vuota.

Un worker Playwright non è incluso e non deve girare nella richiesta PHP dell’hosting condiviso.

## Interventi in sviluppo successivi alla 2.2.9

- Regole per Google reCAPTCHA e l'adapter reCAPTCHA v3 di Convert Forms, con precedenza alle regole approvate dall'amministratore.
- Attivazione dei JS gestiti in sequenza e proxy YOOtheme `$load` ancora invocabile.
- Il click sui placeholder iframe generici non aggira più il consenso.
- **Non è una soluzione universale verificata:** lo scanner PHP non osserva cookie JavaScript, né può garantire il blocco di tutti gli script dinamici. Servono test browser e analisi dei servizi non censiti prima di dichiarare conformità.
