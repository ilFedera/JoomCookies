# JoomCookies

Versione **2.1.0**: package con componente amministrativo `com_joomcookies` e plugin di sistema `plg_system_joomcookies`.

Estensione Joomla 5 e Joomla 6 per la gestione del consenso cookie e il **blocco preventivo** dei servizi non essenziali. Il pacchetto è autonomo: non usa CookieYes, CMP commerciali, CDN o servizi esterni.

JoomCookies facilita gli adempimenti GDPR/ePrivacy. **Non dichiara una conformità legale automatica** per qualsiasi sito.

## Contenuto del pacchetto

Il file installabile è:

`dist/pkg_joomcookies-2.1.0.zip`

Installa il componente **JoomCookies** e il plugin di sistema `System - JoomCookies`. Non modifica core, template, articoli, moduli o altre estensioni. In aggiornamento da V1 i parametri del plugin sono conservati e importati nel componente in modo idempotente.

## Requisiti

- Joomla 5.0 o successivo, oppure Joomla 6.x
- PHP 8.1 o successivo (Joomla 6 richiede PHP 8.3)
- Un sito di **test** su cui installare il pacchetto

## Installazione

1. In amministrazione Joomla aprire **Sistema → Installazione → Estensioni**.
2. Caricare `dist/pkg_joomcookies-2.1.0.zip`.
3. Verificare che il plugin **Sistema - JoomCookies** sia abilitato e che il componente compaia in **Componenti → JoomCookies**.
4. In **Componenti → JoomCookies** usare Panoramica, Scansione, Servizi, Banner e Testi. I parametri del plugin restano un fallback (origine configurazione: Auto / Componente / Plugin).
5. In alternativa, **Sistema → Plugin**, cercare `JoomCookies` e configurare:
   - URL di Cookie Policy e Privacy Policy di **quel** sito
   - versione del consenso
   - testi, colori e posizione del banner
   - eventuali servizi extra oltre al catalogo incorporato
6. Nel frontend, in una finestra anonima, verificare banner, rifiuto, accettazione e revoca.

Per aggiornare, reinstallare un ZIP successivo con `method="upgrade"`. Aumentare **Versione del consenso** se l'informativa o i servizi cambiano in modo sostanziale.

## Sintassi YouTube

Negli articoli, nei moduli HTML (con "Prepara contenuto" attivo) o nel markup:

```text
{joomcookies youtube="VIDEO_ID" title="Titolo" ratio="16:9"}
```

Sono riconosciuti anche gli iframe YouTube già presenti nella pagina. Prima del consenso non viene creato un iframe e non parte una connessione a YouTube, salvo l'opzione esplicita "Miniatura YouTube".

Per riaprire le preferenze:

```text
{joomcookies-preferences}
```

## Categorie

| Categoria | Stato iniziale |
|-----------|----------------|
| Necessari | sempre attivi |
| Statistiche | disattivate |
| Marketing | disattivato |
| Contenuti esterni | disattivati |

Accetta tutti, Rifiuta tutti e Personalizza hanno lo stesso peso. Nessuna categoria facoltativa è preselezionata.

## Documentazione

- `CHANGELOG.md` — versione 1.0.0
- `docs/TECHNICAL.md` — architettura e API
- `docs/SCAN.md` — scansione HTTP, SSRF e contratto Playwright
- `docs/PRIVACY-CONSENT.md` — conservazione del consenso e limiti probatori
- `docs/MIGRATION.md` — uscita da CookieYes o altri CMP
- `docs/TEST-CHECKLIST.md` — prove browser e rete
- `docs/JOOMLA-COMPATIBILITY.md` — cosa è verificato su Joomla 5/6

## Build locale

```bash
php tests/run.php
php build.php
php -S 127.0.0.1:8765
```

Aprire `http://127.0.0.1:8765/tests/preview/index.html` per una prova del banner senza Joomla. Non sostituisce l'installazione su un CMS di test.

## Licenza

GNU GPL versione 2 o successiva. Vedi `LICENSE.txt`.
