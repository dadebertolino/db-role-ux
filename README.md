# DB Role UX

Backend semplificato per gli utenti **non amministratori**: toglie dalla bacheca di WordPress i widget, le voci della admin bar e del menu che a docenti, alunni e personale non servono, e mette a disposizione una pagina **"Area riservata"** con card diverse a seconda del ruolo.

Nato per il sito dell'**IIS Cigna-Baruffi-Garelli** di Mondovì, come prima fase della governance di ruoli e accessi.

- **Autore:** Davide Bertolino — [davidebertolino.it](https://www.davidebertolino.it)
- **Licenza:** GPL v2 o successiva
- **Requisiti:** WordPress 5.8+, PHP 7.4+
- **Niente registrazioni, niente nag, niente trucchi.** Nessun dato salvato nel database.

## Principio fail-safe

L'amministratore (chi ha `manage_options`) **vede sempre tutto**. La pulizia colpisce solo chi ha esplicitamente meno privilegi: se qualcosa va storto, il plugin mostra troppo, mai troppo poco.

## Cosa fa

Per gli utenti target (predefinito: tutti i non amministratori):

1. **Bacheca** — rimuove i widget dichiarati in configurazione (oggi: i tre widget delle circolari e "Menu utente"), nasconde i pulsanti *Aiuto* e *Impostazioni schermata*, blocca la bacheca su una colonna.
2. **Admin bar** — rimuove logo WordPress, aggiornamenti, commenti, palette comandi, "+ Nuovo" e le voci del tema Design Scuole.
3. **Menu laterale** — rimuove le voci elencate in `admin_menu_pages` (vuoto per ora).
4. **Redirect dopo il login** — disattivato; si accende da configurazione.
5. **Shortcode `[db_area_riservata]`** — launchpad a card per capability (`db_area_docenti`, `db_area_alunni`, `ms_gestisci_moduli`). Chi non è loggato viene mandato al login.

## Configurazione

Tutta la policy vive in un array dichiarativo, `db_role_ux_config()` in `inc/config.php`. Si può sovrascrivere senza toccare il plugin, per esempio da un mu-plugin:

```php
add_filter( 'db_role_ux_config', function ( $config ) {
	$config['admin_menu_pages'] = array( 'edit-comments.php', 'tools.php' );
	$config['login_redirect']   = array( 'enabled' => true, 'url' => home_url( '/area-riservata/' ) );
	return $config;
} );
```

Modalità di selezione degli utenti (`target_mode`):

| Valore | Chi viene ripulito |
|--------|--------------------|
| `non_admin` | Chiunque sia loggato e non abbia `manage_options` (predefinito) |
| `roles` | Solo i ruoli in `target_roles` |
| `capability` | Solo chi ha la capability in `target_capability` |

Le card del launchpad si modificano con il filtro `db_launchpad_cards`.

Filtri disponibili: `db_role_ux_config`, `db_launchpad_cards`.

## Installazione

1. Carica lo ZIP da *Plugin → Aggiungi nuovo → Carica plugin* e attivalo.
2. **Se era installata la vecchia versione a file singolo** (`wp-content/plugins/db-role-ux.php`): disattivala ed eliminala. Finché resta attiva, la nuova versione non parte e mostra un avviso agli amministratori.

Gli aggiornamenti arrivano automaticamente dalle release GitHub di `dadebertolino/db-role-ux`.

## Struttura

```
db-role-ux/
├── db-role-ux.php        bootstrap, costanti, updater
└── inc/
    ├── config.php        configurazione e selezione utenti
    ├── cleanup.php       bacheca, admin bar, menu, rifiniture
    ├── login-redirect.php
    ├── launchpad.php     shortcode [db_area_riservata]
    └── class-updater.php aggiornamenti da GitHub
```

## Changelog

### 1.1.0
- Trasformato in plugin standard con cartella dedicata, installabile da ZIP.
- Aggiornamenti automatici da GitHub (`DB_GitHub_Updater_V2`).
- Selezione degli utenti unificata in `db_role_ux_user_matches()`, usata sia dalla pulizia sia dal redirect post-login.
- Protezione contro la convivenza con la vecchia versione a file singolo.

### 1.0.0
- Prima versione (file singolo).
