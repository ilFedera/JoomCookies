# Documentazione tecnica JoomCookies 1.0.0

## Architettura

Il package Joomla `pkg_joomcookies` installa un solo plugin di sistema namespaced:

`JoomCookies\Plugin\System\Joomcookies`

Non è richiesto un componente. La configurazione vive nei parametri del plugin, quindi è specifica dell'installazione Joomla.

Eventi sottoscritti tramite `SubscriberInterface` (modello Joomla 5/6):

| Evento | Ruolo |
|--------|--------|
| `onContentPrepare` | Shortcode YouTube e pulsante preferenze negli articoli/moduli preparati |
| `onBeforeCompileHead` | WebAssetManager: CSS/JS locali, variabili colore |
| `onAfterRender` | Riscrittura HTML, iniezione banner e JSON di configurazione |

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

Dopo il consenso il JavaScript locale clona gli script in elementi eseguibili una sola volta (`data-jck-loaded` + chiave anti-duplicato). La revoca di una categoria già accettata ricarica la pagina e tenta di cancellare i cookie di prima parte elencati nel servizio. I cookie HttpOnly o di terzi non sono cancellabili dal browser del sito.

Asset Joomla in `/media/system/`, `/media/vendor/` e `/media/templates/` non vengono toccati. Gli script del plugin hanno `data-jck-ignore` implicito tramite path/id.

## Registro servizi

Il catalogo incorporato copre, tra gli altri: Google Tag Manager, Google Analytics, gtag inline, Hotjar, Meta Pixel, DoubleClick, Ads, AdSense, YouTube, Vimeo, Google Maps, Instagram, TikTok.

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

Dopo il consenso o al click su "Carica video": iframe verso `youtube-nocookie.com` (predefinito). Il click sul singolo video non salva il consenso di categoria; abilita solo quell'embed.

Lightbox e griglie: se inizializzano gli iframe al `DOMContentLoaded`, dopo l'accettazione della categoria "contenuti esterni" può essere necessario un ricaricamento. Viene emesso l'evento `joomcookies:change`.

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
