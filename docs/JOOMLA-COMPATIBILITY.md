# Compatibilità Joomla 5 e Joomla 6

## Scelte di API

Il codice è scritto per le API moderne comuni a Joomla 5 e 6:

- plugin con `services/provider.php` e `Joomla\CMS\Plugin\CMSPlugin`
- `Joomla\Event\SubscriberInterface` e un solo `EventInterface` per listener
- `Factory::getContainer()->get(DatabaseInterface::class)` nello script di installazione (niente `Factory::getDbo()`, rimosso in Joomla 6)
- WebAssetManager e `joomla.asset.json`
- `InstallerScriptInterface` restituito dallo `script.php`, con classe nominata per l'installatore Joomla 5
- PHP 8.1+ (union types e `match` 8.0/8.1, niente sintassi 8.3-only)
- eventi `onAfterRender` / `onBeforeCompileHead` / `onContentPrepare` letti in modo difensivo (`getItem()` oppure argomenti generici)

Non si usano plugin in stile `plgSystem...` con reflection, né API rimosse da Joomla 6.

## Cosa è stato verificato in questo ambiente di sviluppo

- Sintassi PHP 8.4 dei file del plugin
- Validità XML dei manifest
- Integrità del ZIP
- Sintassi JavaScript
- Test automatici di consenso, YouTube, blocco HTML, escaping e assenza di binding a un dominio sito

## Cosa non è stato verificato

In questo ambiente **non** è disponibile un'installazione Joomla 5 né Joomla 6.

Non risultano quindi provate, su CMS reale:

- la procedura di installazione/upgrade nel backend
- il caricamento del service provider
- WebAssetManager sul frontend di un template reale
- l'ordine rispetto ad altri plugin di sistema
- lightbox/griglie di template specifici
- ACL del plugin in amministrazione

Queste prove vanno eseguite su un clone di test, mai in produzione, seguendo `docs/TEST-CHECKLIST.md`.
