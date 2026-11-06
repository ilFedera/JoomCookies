# Scansione JoomCookies V2

## Scansione HTTP (operativa)

Eseguita dal componente, solo da amministratori con permesso `joomcookies.scan` o `core.admin`, con token CSRF.

- Partenza da sitemap.xml, robots.txt `Sitemap:` e link interni
- Solo GET, solo host autorizzato del sito
- Ogni redirect è rivalutato dal filtro SSRF
- Blocco di loopback, reti private, metadata cloud, localhost, file://, credenziali in URL, porte diverse da 80/443
- Non esegue JavaScript
- Non visita `/administrator`, `/api`, login/logout né URL con `task=`
- Distingue `Set-Cookie` **osservati** da riferimenti in sorgente (`document.cookie`, storage)
- La presenza di uno script **non** è prova che abbia creato un cookie
- I match del catalogo sono proposte di categoria, non regole di blocco

## Scansione avanzata Playwright/Chromium

**Non operativa** in questa versione.

Interfaccia: `BrowserScanDriver` / `UnavailableBrowserScanDriver`.

Non viene richiesto Chromium sull'hosting Joomla. Non vengono simulati risultati. Un worker esterno potrà in futuro implementare il contratto per cookie reali, storage, rete e scenari pre/post consenso.

## Blocco preventivo

Le regole V1 incorporate nel plugin restano attive. I servizi trovati dalla scansione diventano regole extra solo dopo **approvazione** e **abilitazione blocco** esplicite.
