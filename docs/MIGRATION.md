# Migrazione da CookieYes o altri CMP

L'installazione di JoomCookies **non** disinstalla altri banner, **non** modifica articoli e **non** cancella cookie già presenti.

I consensi già raccolti da un altro sistema **non** sono considerati trasferibili.

## Procedura consigliata su un sito di test

1. Installare JoomCookies e lasciare l'altro CMP ancora attivo solo il tempo di confrontare i due banner (poi disattivarlo).
2. Disabilitare o disinstallare CookieYes / iubenda / altri plugin CMP.
3. Rimuovere dagli override del template e dai moduli HTML gli script del vecchio banner e i loro loader (inclusi snippet in `custom.js` o GTM).
4. Rimuovere preconnect/prefetch verso i domini del vecchio CMP.
5. Spegnere in GTM i tag del CMP precedente se GTM resta in uso: GTM stesso resta bloccato da JoomCookies fino al consenso statistica/marketing, a seconda della classificazione.
6. Incrementare la versione del consenso JoomCookies in modo che tutti i visitatori rivedano la scelta.
7. Verificare in finestra anonima che sia visibile **un solo** banner.
8. Aggiornare Cookie Policy e Privacy Policy con i nuovi strumenti e il nuovo cookie tecnico `joomcookies_consent`.

## Contenuti YouTube già in pagina

Gli iframe YouTube esistenti vengono trasformati in segnaposto senza modificare il database degli articoli. Lo shortcode `{joomcookies youtube="ID"}` è facoltativo e può essere introdotto gradualmente.
