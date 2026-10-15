# DB Role UX

Backend semplificato per gli utenti **non amministratori**: toglie dalla bacheca di WordPress i widget, le voci della admin bar e del menu che a docenti, alunni e personale non servono, e mette a disposizione una pagina **"Area riservata"** con card diverse a seconda del ruolo.

Nato per il sito dell'**IIS Cigna-Baruffi-Garelli** di Mondovì, come prima fase della governance di ruoli e accessi.

- **Autore:** Davide Bertolino — [davidebertolino.it](https://www.davidebertolino.it)
- **Licenza:** GPL v2 o successiva
- **Requisiti:** WordPress 6.0+, PHP 7.4+
- **Niente registrazioni, niente nag, niente trucchi.** Nessun dato personale: nel database solo la configurazione della bacheca per ruolo.

## Principio fail-safe

L'amministratore (chi ha `manage_options`) **vede sempre tutto**. La pulizia colpisce solo chi ha esplicitamente meno privilegi: se qualcosa va storto, il plugin mostra troppo, mai troppo poco.

## Cosa fa

Per gli utenti target (predefinito: tutti i non amministratori):

1. **Bacheca** — rimuove i widget dichiarati in configurazione (oggi: i tre widget delle circolari e "Menu utente"), nasconde i pulsanti *Aiuto* e *Impostazioni schermata*, blocca la bacheca su una colonna.
2. **Admin bar** — rimuove logo WordPress, aggiornamenti, commenti, palette comandi, "+ Nuovo" e le voci del tema Design Scuole.
3. **Menu laterale** — rimuove le voci elencate in `admin_menu_pages` (vuoto per ora).
4. **Redirect dopo il login** — disattivato; si accende da configurazione.
5. **Shortcode `[db_area_riservata]`** — launchpad a card per capability (`db_area_docenti`, `db_area_alunni`, `ms_gestisci_moduli`). Chi non è loggato viene mandato al login.
6. **Bacheca per ruolo** — da pannello, per ogni ruolo: widget da nascondere, widget da aggiungere, link nel menu laterale e nella admin bar (vedi sotto).

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

## Bacheca per ruolo

In *Strumenti → DB Role UX → Bacheca per ruolo* scegli un ruolo e imposti:

- **Widget da nascondere** — spunti quelli rilevati in bacheca (il plugin annota ID e titolo dei widget quando qualcuno apre la bacheca) o scrivi gli ID a mano. Per il principio fail-safe, ai ruoli con `manage_options` non si nasconde nulla.
- **Widget aggiunti**, in colonna principale o laterale, di tre tipi:
  - *Elenco di link*;
  - *Testo libero* (HTML di base, filtrato con `wp_kses_post`);
  - *Card dell'Area riservata*: i gruppi di `db_launchpad_cards`, visibili solo a chi ha la capability del gruppo.
- **Link nel menu laterale** (sotto *Bacheca*, con icona Dashicons facoltativa) e **nella admin bar** (backend e sito).

I link si scrivono uno per riga, `Etichetta | URL`. L'URL può essere assoluto (`https://…`), una pagina del sito (`/area-riservata/`) o una pagina del backend (`edit.php?post_type=page`). Altri schemi (`javascript:`, `data:`…) vengono scartati.

Un utente con più ruoli riceve l'unione delle impostazioni dei suoi ruoli. Widget e link aggiunti valgono anche per gli amministratori: aggiungono, non tolgono.

Le impostazioni stanno nell'opzione `db_role_ux_roles` ed entrano nella configurazione alla chiave `roles`, prima del filtro `db_role_ux_config`: un mu-plugin può sempre sovrascriverle (la scheda lo segnala).

## Pagina di riepilogo

In *Strumenti → DB Role UX* (solo per chi ha `manage_options`) c'è il riepilogo della policy attiva, già passata dal filtro `db_role_ux_config`:

- quali ruoli hanno il backend semplificato e quanti utenti hanno;
- quali widget, voci della admin bar e voci di menu vengono nascosti;
- se il redirect post-login è acceso e verso dove;
- i gruppi di card dell'Area riservata e quali ruoli li vedono.

Dalla tabella dei ruoli si passa direttamente alla bacheca di ciascun ruolo.

## Dati salvati e disinstallazione

Il plugin non salva dati personali. Usa due opzioni:

| Opzione | Contenuto |
|---------|-----------|
| `db_role_ux_roles` | impostazioni della bacheca per ruolo |
| `db_role_ux_seen_widgets` | ID, titolo e colonna dei widget rilevati in bacheca |

Eliminando il plugin da *Plugin* le due opzioni vengono rimosse (su ogni sito, in multisite).

## Installazione

1. Carica lo ZIP da *Plugin → Aggiungi nuovo → Carica plugin* e attivalo.
2. **Se era installata la vecchia versione a file singolo** (`wp-content/plugins/db-role-ux.php`): disattivala ed eliminala. Finché resta attiva, la nuova versione non parte e mostra un avviso agli amministratori.

Gli aggiornamenti arrivano automaticamente dalle release GitHub di `dadebertolino/db-role-ux`.

## Struttura

```
db-role-ux/
├── db-role-ux.php        bootstrap, costanti, updater
├── uninstall.php         rimozione delle opzioni
├── assets/
│   ├── css/
│   │   ├── db-admin-ui.css   design system condiviso dei plugin DB
│   │   ├── admin.css         pagina Strumenti → DB Role UX
│   │   └── dashboard.css     widget aggiunti in bacheca
│   └── js/admin.js       campi per tipo di widget
└── inc/
    ├── settings.php      bacheca per ruolo: dati, sanitizzazione, unione
    ├── role-dashboard.php bacheca per ruolo: widget, menu, admin bar
    ├── config.php        configurazione e selezione utenti
    ├── cleanup.php       bacheca, admin bar, menu, rifiniture
    ├── login-redirect.php
    ├── launchpad.php     shortcode [db_area_riservata]
    ├── admin.php         pagina Strumenti → DB Role UX (riepilogo e modulo)
    └── class-updater.php aggiornamenti da GitHub
```

## Sviluppo

Test unit, integration ed E2E (wp-env + Playwright) girano su GitHub Actions a ogni push e PR, più una run notturna su WordPress in sviluppo. Comandi e struttura in [TESTING.md](TESTING.md). I file di sviluppo (`tests/`, `bin/`, configurazioni) sono esclusi dallo ZIP della release.

## Changelog

### 1.3.0
- **Bacheca per ruolo**, da *Strumenti → DB Role UX*: per ogni ruolo widget da nascondere (scelti fra quelli rilevati o per ID), widget aggiunti (elenco di link, testo libero, card dell'Area riservata), link nel menu laterale e nella admin bar.
- Fail-safe: ai ruoli con `manage_options` non si nasconde nulla; utenti con più ruoli ricevono l'unione delle impostazioni.
- Il filtro `db_role_ux_config` ha l'ultima parola anche sulle impostazioni da pannello (chiave `roles`).
- Due opzioni nel database (nessun dato personale), rimosse alla disinstallazione da `uninstall.php`.

### 1.2.0
- Pagina di riepilogo in *Strumenti → DB Role UX*, in sola lettura, con il design system condiviso (`db-admin-ui.css`): anteprima per ruolo, cosa viene nascosto, redirect post-login, gruppi dell'Area riservata. Link *Riepilogo* nella lista dei plugin.
- Selezione degli utenti in un'unica policy (`db_role_ux_policy_matches()`), valutata sia sugli utenti sia sui ruoli.
- Requisito minimo portato a WordPress 6.0 (prima 5.8): è la versione più vecchia provata in CI.
- Test unit, integration ed E2E con accessibilità (axe-core), PHPCS (WPCS) e CI su GitHub Actions; release automatica dello ZIP sul tag.

### 1.1.0
- Trasformato in plugin standard con cartella dedicata, installabile da ZIP.
- Aggiornamenti automatici da GitHub (`DB_GitHub_Updater_V2`).
- Selezione degli utenti unificata in `db_role_ux_user_matches()`, usata sia dalla pulizia sia dal redirect post-login.
- Protezione contro la convivenza con la vecchia versione a file singolo.

### 1.0.0
- Prima versione (file singolo).
