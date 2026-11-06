# JoomCookies e LiteSpeed Cache

JoomCookies è pensato per convivere con LiteSpeed Cache **senza** disattivare la cache del sito e **senza** variare le pagine sul cookie di consenso.

## Come funziona

1. PHP genera **un solo HTML pubblico**: script non essenziali in `type="text/plain"` e YouTube come segnaposto.
2. Il consenso vive nel browser (`joomcookies_consent` + `localStorage`).
3. `joomcookies.js` legge quella scelta e attiva script/embed solo per quel visitatore.
4. Una pagina in cache (fredda o calda) resta quindi identica per tutti; il comportamento cambia solo in JavaScript.

Non aggiungere `joomcookies_consent` alle regole **Cache Vary** di LiteSpeed: creerebbe copie HTML inutili e, se combinato con HTML “sbloccato”, rischierebbe di servire script a chi non ha acconsentito.

## Esclusioni strettamente necessarie

Non disabilitare LiteSpeed sul frontend. Escludere **solo** gli asset JoomCookies dalle ottimizzazioni JS/CSS.

In **LiteSpeed Cache → Settings** (nomi esatti dipendono dalla versione del plugin Joomla):

| Funzione | Esclusione |
|----------|------------|
| JS Combine / Minify | `plg_system_joomcookies/js/joomcookies.js` |
| Load JS Deferred | stesso URI, oppure rispettare `data-no-defer` |
| Load JS Delayed | stesso URI, oppure rispettare `data-no-delay` |
| CSS Combine / Minify | `plg_system_joomcookies/css/joomcookies.css` |

Il markup iniettato dal plugin include già:

`data-no-optimize` `data-no-defer` `data-no-delay` `data-no-combine` `data-no-minify`

Se una versione di LiteSpeed ignora questi attributi, le esclusioni URI sopra restano obbligatorie.

## Dopo un aggiornamento JoomCookies

Gli URL CSS/JS portano `?v=` con la versione del package (es. `2.2.9`). Dopo l’upgrade:

1. Svuotare la cache LiteSpeed (HTML + JS/CSS).
2. Non è necessario svuotare i dati di consenso nel browser, salvo cambio di `consent_version`.

## Scenari attesi

| Stato | Risultato atteso |
|-------|------------------|
| Cache fredda, nessun consenso | HTML bloccato, banner, nessuna richiesta YouTube/GTM |
| Cache calda, nessun consenso | Stesso HTML, stesso blocco |
| Cache calda, consenso già salvato | Stesso HTML; JS attiva script e sostituisce i segnaposto YouTube con iframe (`autoplay=0`) |
| Consenso appena salvato | I video in pagina, anche nelle griglie, diventano iframe senza ricaricare e senza svuotare la cache |
| Consenso revocato | Player interrotti; reload predefinito; HTML in cache resta quello bloccato |

## Cosa non è stato verificato qui

HIT/MISS si diagnostica dalle intestazioni `X-LiteSpeed-Cache` / `X-LiteSpeed-Cache-Control` in DevTools: il consenso **non** dipende da esse.

La diagnostica plugin (`diagnostic`) registra solo versione asset, categorie e conteggio iframe, senza dati personali.

L’installazione **non** svuota la cache LiteSpeed in automatico (nessuna API di purge indiscriminato). Dopo l’upgrade svuotare HTML+JS/CSS dal pannello LiteSpeed.

In questo ambiente di sviluppo **non** c’è Joomla 5.3.2 con LiteSpeed. La conferma su cache fredda/calda reale va fatta sul sito di test dopo l’installazione del ZIP 2.2.9.
