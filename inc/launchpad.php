<?php
/**
 * Launchpad "Area riservata": shortcode [db_area_riservata].
 *
 * @package DB_Role_UX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * =====================================================================
 *  5) LAUNCHPAD "AREA RISERVATA"  —  shortcode [db_area_riservata]
 *
 *  Pagina di atterraggio a card per ruolo, da inserire in una pagina
 *  frontend (es. /area-riservata) tramite lo shortcode. Rende card in
 *  stile Bootstrap Italia / Design Scuole Italia (ereditato dal tema).
 *
 *  STATO: versione statica. I link sono definiti nel codice
 *  (db_launchpad_cards()). In Fase launchpad diventeranno gestibili da
 *  pannello admin: la struttura dati resta identica, cambierà solo la
 *  FONTE (DB invece che codice). Nessun lavoro buttato.
 *
 *  PROTEZIONE: server-side. Se l'utente non è loggato → redirect al
 *  login con ritorno a questa pagina. Le card mostrate dipendono dalle
 *  CAPABILITY dell'utente, non dallo slug del ruolo (coerente con tutto
 *  il resto: un domani un coordinatore con db_area_docenti vede le
 *  stesse card dei docenti senza modifiche).
 * =====================================================================
 */

/**
 * Definizione dichiarativa delle card, raggruppate per la CAPABILITY
 * che le sblocca. Ogni card: titolo, descrizione, url, icona (nome
 * icona Bootstrap Italia, es. 'it-calendar', 'it-file', 'it-user').
 *
 * PLACEHOLDER realistici: da correggere con i link reali. La 'capability'
 * di ogni gruppo determina chi vede quel blocco di card.
 */
function db_launchpad_cards() {

	$groups = array(

		// Card per i DOCENTI (chi ha db_area_docenti).
		'db_area_docenti'    => array(
			'label' => 'Area Docenti',
			'cards' => array(
				array(
					'title' => 'Programmazioni',
					'desc'  => 'Consulta e scarica le programmazioni didattiche.',
					'url'   => '#programmazioni',
					'icon'  => 'it-file',
				),
				array(
					'title' => 'Orario delle lezioni',
					'desc'  => 'Il tuo orario settimanale aggiornato.',
					'url'   => '#orario',
					'icon'  => 'it-calendar',
				),
				array(
					'title' => 'Ricevimento genitori',
					'desc'  => 'Gestisci e consulta gli appuntamenti.',
					'url'   => '#ricevimento',
					'icon'  => 'it-user',
				),
				array(
					'title' => 'Circolari storiche',
					'desc'  => 'Archivio delle circolari degli anni precedenti.',
					'url'   => '#circolari-storiche',
					'icon'  => 'it-inbox',
				),
			),
		),

		// Card per gli ALUNNI (chi ha db_area_alunni).
		'db_area_alunni'     => array(
			'label' => 'Area Alunni',
			'cards' => array(
				array(
					'title' => 'Materiali didattici',
					'desc'  => 'Dispense e materiali condivisi dai docenti.',
					'url'   => '#materiali',
					'icon'  => 'it-file',
				),
				array(
					'title' => 'Orario delle lezioni',
					'desc'  => 'Consulta l\'orario della tua classe.',
					'url'   => '#orario-alunni',
					'icon'  => 'it-calendar',
				),
			),
		),

		// Card per la SEGRETERIA (capability ms_ di Moduli Scuola).
		'ms_gestisci_moduli' => array(
			'label' => 'Segreteria',
			'cards' => array(
				array(
					'title' => 'Gestione moduli',
					'desc'  => 'Moduli e richieste da gestire.',
					'url'   => '#moduli',
					'icon'  => 'it-files',
				),
				array(
					'title' => 'Approvazione risposte',
					'desc'  => 'Risposte in attesa di approvazione.',
					'url'   => '#approvazioni',
					'icon'  => 'it-check-circle',
				),
			),
		),
	);

	return apply_filters( 'db_launchpad_cards', $groups );
}

/**
 * Registra lo shortcode.
 */
add_shortcode( 'db_area_riservata', 'db_launchpad_render' );

/**
 * Render dello shortcode.
 */
function db_launchpad_render( $atts = array() ) {

	// --- PROTEZIONE SERVER-SIDE -------------------------------------
	// Utente non loggato → redirect al login, con ritorno a questa pagina.
	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( get_permalink() );
		// wp_redirect va emesso prima dell'output; a livello di shortcode
		// il body è già iniziato, quindi usiamo un redirect JS + fallback link.
		return sprintf(
			'<script>window.location.href=%s;</script><p>Per accedere all\'area riservata devi <a href="%s">effettuare il login</a>.</p>',
			wp_json_encode( esc_url_raw( $login_url ) ),
			esc_url( $login_url )
		);
	}

	$user   = wp_get_current_user();
	$name   = $user->display_name ? $user->display_name : $user->user_login;
	$groups = db_launchpad_cards();

	ob_start();
	?>
	<div class="db-launchpad container my-4">

		<div class="db-launchpad__header mb-4">
			<h1 class="h2 mb-1">Area riservata</h1>
			<p class="lead mb-0">Ciao <?php echo esc_html( $name ); ?>, ecco le funzioni disponibili per te.</p>
		</div>

		<?php
		$shown_any = false;

		foreach ( $groups as $capability => $group ) {

			// Mostra il blocco solo se l'utente ha la capability richiesta.
			if ( ! current_user_can( $capability ) ) {
				continue;
			}

			$cards = isset( $group['cards'] ) ? $group['cards'] : array();
			if ( empty( $cards ) ) {
				continue;
			}

			$shown_any = true;
			?>
			<section class="db-launchpad__group mb-5">
				<h2 class="h4 mb-3"><?php echo esc_html( $group['label'] ); ?></h2>

				<div class="row">
					<?php
					foreach ( $cards as $card ) :
						$title = isset( $card['title'] ) ? $card['title'] : '';
						$desc  = isset( $card['desc'] ) ? $card['desc'] : '';
						$url   = isset( $card['url'] ) ? $card['url'] : '#';
						$icon  = isset( $card['icon'] ) ? $card['icon'] : 'it-link';
						?>
						<div class="col-12 col-md-6 col-lg-4 mb-4">
							<a href="<?php echo esc_url( $url ); ?>" class="text-decoration-none">
								<div class="card-wrapper card-space h-100">
									<div class="card card-bg h-100">
										<div class="card-body">
											<div class="categoryicon-top mb-2">
												<svg class="icon icon-primary" aria-hidden="true">
													<use xlink:href="#<?php echo esc_attr( $icon ); ?>"></use>
												</svg>
											</div>
											<h3 class="card-title h5"><?php echo esc_html( $title ); ?></h3>
											<p class="card-text"><?php echo esc_html( $desc ); ?></p>
										</div>
									</div>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		// Nessuna card per questo utente: messaggio neutro (fail-safe informativo).
		if ( ! $shown_any ) {
			echo '<div class="alert alert-info" role="alert">Al momento non ci sono funzioni disponibili per il tuo profilo. Se ritieni sia un errore, contatta la segreteria.</div>';
		}
		?>

	</div>
	<?php
	return ob_get_clean();
}

