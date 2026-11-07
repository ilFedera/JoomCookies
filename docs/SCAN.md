# Scansione JoomCookies V2

## Scansione HTTP (operativa)

Eseguita dal componente, solo da amministratori con permesso `joomcookies.scan` o `core.admin`, con token CSRF.

- Modalità **rapida**: limite di pagine configurabile (predefinito 50)
- Modalità **completa**: nessun tetto arbitrario di pagine; restano limiti di sicurezza su dimensione risposta, timeout, lotti e ritardo tra richieste
- Coda persistente `#__joomcookies_scan_queue`
- `scan.start` crea il job e il primo lotto; `scan.batch` continua da solo (fetch in loop; se il JS non parte, un form nascosto ripubblica il lotto dopo 2s)
- Pausa / Riprendi / Annulla sono POST autonomi
- Il limite «secondi» è **per richiesta PHP** (3–25), non la durata totale della scansione completa
- Scansione rapida: il tetto pagine si applica alle URL **analizzate** (`done`), non a quelle solo in coda
- Pausa, ripresa e annullamento; i risultati già salvati restano
- Fonti URL: sitemap XML, sitemap index, menu Joomla pubblici, link interni, URL canonici
- Solo GET, solo host autorizzato del sito
- Ogni redirect è rivalutato dal filtro SSRF
- Blocco di loopback, reti private, metadata cloud, localhost, file://, credenziali in URL, porte diverse da 80/443
- Non esegue JavaScript
- Non visita `/administrator`, `/api`, login/logout né URL con `task=`
- Distingue `Set-Cookie` **osservati** da riferimenti in sorgente (`document.cookie`, storage)
- La presenza di uno script **non** è prova che abbia creato un cookie
- I match del catalogo sono proposte di categoria, non regole di blocco
- I risultati sono raggruppati per tipo, nome, dominio e servizio
- Un confronto con una scansione incompleta non marca i servizi mancanti come rimossi

## Scansione avanzata Playwright/Chromium

**Non operativa senza un worker esterno autorizzato** in questa versione.

Interfaccia: `BrowserScanDriver` / `UnavailableBrowserScanDriver`.

Non viene richiesto Chromium sull'hosting Joomla. Non viene avviato un worker remoto. Non vengono simulati risultati.

Un worker esterno autorizzato potrà in futuro implementare il contratto per:

1. cookie creati in navigazione
2. localStorage e sessionStorage
3. richieste verso domini esterni
4. caricamento iframe
5. comportamento prima del consenso, dopo il rifiuto e dopo l'accettazione

Il processo deve restare fuori dalle richieste PHP del sito e non deve partire automaticamente su hosting condiviso. Il contratto richiesto al worker è: contesti browser isolati per URL/scenario (`before_consent`, categoria singola, `accept_all`), metadati cookie senza valori (nome, dominio, path, expires/max-age, Secure, HttpOnly, SameSite, first/third party), URL di osservazione e, se disponibile, URL della risposta Set-Cookie. JoomCookies non invia navigazione a servizi esterni e non installa Playwright/Chromium.

## Blocco preventivo

Le regole V1 incorporate nel plugin restano attive. I servizi trovati dalla scansione diventano regole extra solo dopo **approvazione** e **abilitazione blocco** esplicite.
