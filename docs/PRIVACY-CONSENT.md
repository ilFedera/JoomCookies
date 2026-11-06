# Consenso, privacy e limiti probatori

## Cosa memorizza JoomCookies

Solo la scelta dell'utente:

- categorie accettate o rifiutate
- versione dell'informativa (`consent_version`)
- data/ora Unix della scelta

Il record sta nel cookie tecnico di prima parte `joomcookies_consent` (letto anche da JavaScript, quindi **non** HttpOnly) e, se il browser lo consente, in `localStorage` con chiave `joomcookies.consent`.

Non vengono inviati consensi a server JoomCookies, CDN o CMP. Non viene raccolto l'IP, l'user-agent o un identificativo di profilazione da questa estensione.

## Cosa non è

- Non è un registro server-side delle prove di consenso.
- Non sostituisce il registro dei trattamenti.
- Non dimostra da sola, in un contenzioso, chi ha cliccato e da quale dispositivo, oltre a quanto il browser ha conservato in locale.
- Non trasferisce consensi raccolti da CookieYes o da altri strumenti.

Se servono prove più robusthe (account utente, log server, timestamp firmati) occorre un'integrazione applicativa esterna a questa V1.

## Cookie di profilazione

Il cookie di consenso non è usato per pubblicità, misurazione o tracciamento. La durata è configurabile (predefinito 180 giorni).

I cookie dei servizi terzi (`_ga`, `_fbp`, ecc.) possono essere creati **solo dopo** l'attivazione dello script corrispondente. In revoca JoomCookies prova a cancellare i nomi dichiarati sul dominio del sito. Non può cancellare cookie di altri domini né quelli HttpOnly.

## Miniature YouTube

La modalità predefinita è locale. Abilitare "Miniatura YouTube" fa partire una richiesta a `i.ytimg.com` prima del consenso: è una scelta amministrativa esplicita e va descritta nell'informativa se usata.

`youtube-nocookie.com` riduce alcuni cookie YouTube ma **non** elimina il trasferimento di dati verso Google al caricamento del player.
