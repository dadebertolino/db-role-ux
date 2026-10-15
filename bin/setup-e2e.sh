#!/usr/bin/env bash
#
# Prepara l'ambiente wp-env per gli E2E di DB Role UX. Idempotente: si può
# rilanciare sullo stesso ambiente. Fallisce (set -e) sui passi essenziali.
#
#  - permalink pretty (la pagina /area-riservata/);
#  - plugin attivo;
#  - ruolo "docente" con la capability db_area_docenti (card Area Docenti);
#  - utenti docente e sottoscrittore (password: password);
#  - pagina "Area riservata" con lo shortcode [db_area_riservata].
#
# Widget delle circolari, voci della admin bar del tema e redirect post-login
# acceso arrivano dal mu-plugin tests/fixtures/db-role-ux-e2e-fixture.php.
#
set -euo pipefail

run() { npx wp-env run cli wp "$@"; }

echo "→ Permalink pretty"
run rewrite structure '/%postname%/' --hard
run rewrite flush --hard

echo "→ DB Role UX attivo"
run plugin activate db-role-ux || true
if ! run plugin is-active db-role-ux 2>/dev/null; then
	echo "::error::DB Role UX non attivo. Setup fallito." >&2
	run plugin list
	exit 1
fi

echo "→ Ruolo docente"
run role exists docente >/dev/null 2>&1 || run role create docente Docente --clone=subscriber
run cap add docente db_area_docenti

echo "→ Utenti di test"
ensure_user() {
	if ! run user get "$1" --field=ID >/dev/null 2>&1; then
		run user create "$1" "$1@example.test" --role="$2" --user_pass=password --display_name="$3"
	fi
}
ensure_user docente docente 'Prof. Rossi'
ensure_user sottoscrittore subscriber 'Mario Bianchi'

echo "→ Pagina Area riservata"
if [ -z "$(run post list --post_type=page --name=area-riservata --field=ID --format=ids 2>/dev/null | tr -d '[:space:]')" ]; then
	run post create --post_type=page --post_status=publish --post_title='Area riservata' \
		--post_name=area-riservata --post_content='[db_area_riservata]'
fi

echo "Setup E2E completato."
