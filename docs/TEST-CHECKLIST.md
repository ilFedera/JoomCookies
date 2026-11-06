# Checklist browser (da eseguire sul sito Joomla di test)

Ambiente richiesto: Joomla 5 e, se disponibile, Joomla 6. Finestra anonima, DevTools → Network con cache disabilitata.

## Prima di qualsiasi scelta

- [ ] Compare il banner con Accetta tutti, Rifiuta tutti, Personalizza.
- [ ] Rifiuta tutti è visibile quanto Accetta tutti.
- [ ] Nessuna categoria facoltativa è già selezionata.
- [ ] Nessuna richiesta a `youtube.com`, `youtube-nocookie.com`, `googletagmanager.com`, `google-analytics.com`, `connect.facebook.net` (salvo risorse non gestite dal registro).
- [ ] I video YouTube mostrano il segnaposto locale, non un iframe.
- [ ] Navigazione da tastiera: Tab cicla nei pulsanti, Escape non nasconde il banner se manca ancora una scelta.

## Dopo Rifiuta tutti

- [ ] Il banner si chiude, resta il pulsante preferenze.
- [ ] Cookie `joomcookies_consent` presente, categorie facoltative a false, campo versione e timestamp valorizzati.
- [ ] Ricaricando la pagina il banner non ricompare.
- [ ] Gli script statistica/marketing restano `type="text/plain"`.
- [ ] Un click su "Carica il video" senza consenso apre le preferenze e non crea l'iframe.

## Dopo Accetta tutti

- [ ] Partono gli script registrati (una sola volta ciascuno).
- [ ] I player YouTube **non** partono da soli: compare "Carica il video". Dopo il clic il player usa `youtube-nocookie.com` se l'opzione è attiva.
- [ ] Lightbox/griglia: i segnaposto restano 16:9 e leggibili; il video parte solo dopo il clic esplicito.

## Revoca

- [ ] Riaprire le preferenze, disattivare una categoria già accettata, salvare.
- [ ] La pagina si ricarica.
- [ ] I servizi di quella categoria non partono più.
- [ ] I cookie di prima parte elencati nel servizio risultano assenti, se il browser li espone.

## Cambio informativa

- [ ] In amministrazione aumentare `consent_version`.
- [ ] Al reload frontend il banner ricompare anche con cookie precedente.

## Diagnostica

- [ ] Abilitare la modalità diagnostica solo in test.
- [ ] In console compaiono i log `JoomCookies:`.
- [ ] Nel sorgente HTML è presente il commento `joomcookies-blocked`.

## Accessibilità e viewport

- [ ] Desktop, tablet, smartphone: pulsanti usabili, testo leggibile, nessun overlay che copre i controlli.
- [ ] Focus visibile su pulsanti e switch.
