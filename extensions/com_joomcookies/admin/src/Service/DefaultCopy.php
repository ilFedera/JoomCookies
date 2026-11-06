<?php

/**
 * @package     JoomCookies
 * @subpackage  com_joomcookies
 */

namespace JoomCookies\Component\Joomcookies\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Italian default copy for new installs. Existing keys are never overwritten.
 */
final class DefaultCopy
{
    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'enabled'              => 1,
            'consent_version'      => '1',
            'position'             => 'bottom',
            'show_reopen_button'   => 1,
            'youtube_nocookie'     => 1,
            'youtube_thumbnails'   => 'local',
            'text_title'           => 'La tua privacy è importante',
            'text_message'         => 'Utilizziamo cookie tecnici necessari al funzionamento del sito e, solo con il tuo consenso, cookie e servizi esterni per statistiche, contenuti multimediali e altre funzionalità. Puoi accettare, rifiutare quelli non necessari oppure personalizzare le tue preferenze. Potrai modificarle in qualsiasi momento.',
            'text_accept'          => 'Accetta tutti',
            'text_reject'          => 'Rifiuta tutti',
            'text_customize'       => 'Personalizza',
            'text_save'            => 'Salva preferenze',
            'text_close'           => 'Chiudi',
            'text_reopen'          => 'Preferenze sulla privacy',
            'text_prefs_title'     => 'Preferenze sulla privacy',
            'text_prefs_desc'      => 'Scegli quali categorie di cookie e servizi autorizzare. I cookie strettamente necessari rimangono sempre attivi perché indispensabili al funzionamento del sito.',
            'text_cookie_link'     => 'Cookie policy',
            'text_privacy_link'    => 'Privacy policy',
            'text_youtube_blocked' => 'Video YouTube bloccato',
            'text_youtube_help'    => 'Attiva i contenuti esterni per visualizzarlo.',
            'text_youtube_load'    => 'Carica il video',
            'text_youtube_prefs'   => 'Gestisci preferenze',
            'text_embed_blocked'   => 'Contenuto esterno bloccato',
            'text_embed_load'      => 'Carica contenuto',
            'text_embed_help'      => 'Per usare i moduli protetti da reCAPTCHA apri le preferenze e autorizza Contenuti esterni.',
        ];
    }

    /**
     * @return list<array{alias: string, title: string, description: string, required: int, ordering: int}>
     */
    public static function categories(): array
    {
        return [
            [
                'alias'       => 'necessary',
                'title'       => 'Necessari',
                'description' => 'Cookie e funzionalità indispensabili per sicurezza, navigazione e corretto funzionamento del sito. Sempre attivi.',
                'required'    => 1,
                'ordering'    => 1,
            ],
            [
                'alias'       => 'statistics',
                'title'       => 'Statistiche',
                'description' => 'Strumenti utilizzati per analizzare l\'utilizzo del sito e migliorarne i contenuti. Attivati soltanto dopo il consenso.',
                'required'    => 0,
                'ordering'    => 2,
            ],
            [
                'alias'       => 'marketing',
                'title'       => 'Marketing',
                'description' => 'Strumenti utilizzati per pubblicità, promozione e misurazione delle campagne. Attivati soltanto dopo il consenso.',
                'required'    => 0,
                'ordering'    => 3,
            ],
            [
                'alias'       => 'embeds',
                'title'       => 'Contenuti esterni',
                'description' => 'Video, mappe, social network, reCAPTCHA e altri contenuti incorporati da servizi esterni. Attivati soltanto dopo il consenso. Serve per inviare i moduli protetti da reCAPTCHA.',
                'required'    => 0,
                'ordering'    => 4,
            ],
        ];
    }
}
