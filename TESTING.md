# Test & CI — DB Role UX

Piramide dei test su GitHub Actions: `.github/workflows/ci.yml` a ogni push su
`main` e a ogni PR, `.github/workflows/nightly.yml` ogni notte. Gli E2E stanno
in un workflow riutilizzabile (`.github/workflows/e2e.yml`) chiamato da
entrambi. La release resta in `.github/workflows/release.yml`, sul tag `v*`.

## I job della CI

| Job | Cosa verifica | Quando |
|-----|---------------|--------|
| **lint** | `php -l` su PHP 7.4 e 8.3 | ogni push/PR |
| **phpcs** | WPCS Extra (sicurezza ed escaping bloccanti) + compatibilità PHP 7.4+ | ogni push/PR |
| **unit** | policy, pulizia, redirect, launchpad, pagina di riepilogo; PHP 7.4–8.5 | ogni push/PR |
| **integration** | WordPress + MySQL reali: 6.0 (minimo dichiarato), ultima versione, ultima versione multisite | dopo lint |
| **e2e** | wp-env + Playwright: `.wp-env.json` e WordPress 6.0 (minimo dichiarato) | dopo lint, phpcs e unit |

`composer.lock` non è versionato (solo dipendenze di sviluppo); le dipendenze
npm sono fissate da `package-lock.json`.

## Run notturna

Ogni notte alle 04:27 UTC (e a mano da *Actions → Nightly*): E2E su
WordPress trunk × PHP 8.3, ultima release × PHP 8.3 e 8.4, WordPress 6.0 ×
PHP 8.1; integration su WordPress trunk × PHP 8.4.

## Perché questa struttura

Il plugin decide **chi** vede un backend ridotto. L'errore da non fare mai è
nascondere qualcosa all'amministratore (principio fail-safe), quindi ogni
livello lo verifica:

- **unit** — bacheca per ruolo: URL ammessi e scartati, parsing dei link,
  widget per tipo, ID univoci, unione dei ruoli, fail-safe su
  `manage_options`, filtro con l'ultima parola, modulo e salvataggio (nonce,
  capability, ruolo inesistente);
  `db_role_ux_policy_matches()` in tutte le modalità
  (`non_admin`, `roles`, `capability`, sconosciuta), `manage_options` sempre
  escluso anche se assegnato al singolo utente; i callback di pulizia
  chiamati solo per gli utenti target; escape dell'output del launchpad
  (card dal filtro con HTML e `javascript:`); pagina di riepilogo.
- **integration** — gli stessi contratti con `WP_User`, `WP_Role`,
  `WP_Admin_Bar`, meta box e `$menu` reali; super admin del multisite;
  filtro `login_redirect` di WordPress; shortcode con `do_shortcode()`;
  bacheca per ruolo con opzioni e meta box reali, widget aggiunti dopo la
  tabula rasa, salvataggio con nonce veri, `uninstall.php` (anche multisite).
- **e2e** — ciò che vede davvero un sottoscrittore, un docente e un
  amministratore nel browser, più axe-core (WCAG 2.1 AA) su pagina di
  riepilogo e launchpad.

## Unit

`tests/unit/bootstrap.php` stubba il minimo di WordPress (filtri con
priorità, utenti, ruoli, capability, meta box, admin bar, escape) e carica i
file di `inc/` tranne l'updater; `db-role-ux.php` non si carica perché
istanzia l'updater. Gli hook agganciati al caricamento si conservano fra un
test e l'altro, i filtri aggiunti dai test no (`dbru_test_reset()`).

```bash
composer install
composer test:unit
composer phpcs
```

## Integration

Servono MySQL e la WordPress test suite:

```bash
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
WP_MULTISITE=1 WP_TESTS_DIR=/tmp/wordpress-tests-lib composer test:integration
```

## E2E

Servono Docker e Node 22.

```bash
npm ci
npx playwright install chromium
npm run env:start
npm run env:setup     # ruolo docente, utenti, pagina /area-riservata/
npm run test:e2e
```

`bin/setup-e2e.sh` è idempotente: crea il ruolo `docente` (con
`db_area_docenti`), gli utenti `docente` e `sottoscrittore` (password
`password`) e la pagina con `[db_area_riservata]`.

Il mu-plugin `tests/fixtures/db-role-ux-e2e-fixture.php` simula ciò che sul
sito della scuola arriva da altri plugin e dal tema: i widget delle circolari
con gli ID `0`–`3`, le voci `design-scuole` e `manuale` della admin bar, più
un widget (`e2e_widget_libero`) e una voce **non** dichiarati che devono
restare. Accende anche il redirect post-login verso `/area-riservata/` ed
espone `POST /?rest_route=/dbru-e2e/v1/reset`, che azzera la bacheca per
ruolo prima e dopo `role-dashboard.spec.js`.

| Spec | Cosa copre |
|------|------------|
| `cleanup.spec.js` | bacheca, admin bar (admin e frontend), Aiuto, Impostazioni schermata, una colonna; l'admin vede tutto |
| `login-redirect.spec.js` | sottoscrittore e docente sull'Area riservata, admin nella bacheca |
| `launchpad.spec.js` | ospite al login con ritorno, card per capability, messaggio senza card |
| `admin-page.spec.js` | pagina di riepilogo, link nella lista plugin, 403 per i non admin |
| `role-dashboard.spec.js` | bacheca per ruolo dal pannello alla bacheca: rilevazione dei widget, campi per tipo, widget nascosti e aggiunti, menu e admin bar per il docente; sottoscrittore e admin invariati; axe-core su modulo e widget |
| `a11y.spec.js` | axe-core WCAG 2.1 AA sull'output del plugin |
