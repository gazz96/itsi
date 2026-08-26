<?php
/**
 * LP2M Hibah Receiver — Form pendaftaran + REST API
 *
 * Semua data disimpan sebagai CPT `pendaftaran_hibah` + post meta.
 *
 * @package itsi
 */

defined( 'ABSPATH' ) || exit;

class ITSI_LP2M_Hibah_Receiver {

	/** Status yang diperbolehkan untuk field `_status`. */
	public const STATUSES = [
		'submitted', 'under_review', 'revised', 'approved', 'rejected', 'done',
	];

	/** ID post terakhir yang disimpan (dipakai untuk link admin di email). */
	private int $last_post_id = 0;

	public function init(): void {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		// Admin list columns — tampilkan meta yang disubmit di ?post_type=pendaftaran_hibah.
		add_filter( 'manage_pendaftaran_hibah_posts_columns', [ $this, 'admin_columns' ] );
		add_action( 'manage_pendaftaran_hibah_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'admin_list_styles' ] );
		// Kirim email manual ke pemohon dari wp-admin (post.php) & list.
		add_action( 'post_action_lp2m_send_email', [ $this, 'handle_admin_send_email' ] );
		add_action( 'admin_post_lp2m_send_email', [ $this, 'handle_admin_send_email_list' ] );
		add_action( 'wp_ajax_lp2m_inline_status', [ $this, 'ajax_inline_status' ] );
		add_action( 'wp_ajax_lp2m_inline_email', [ $this, 'ajax_inline_email' ] );
		// CPT & metabox pendaftaran konsisten via TypeRocket (seperti Detail Hibah di functions.php).
		add_action( 'typerocket_loaded', [ $this, 'register_tr_cpt_and_metabox' ] );
		// Sinkron _proposal_id (TR File) ↔ _proposal_url kanonik + validasi PDF.
		add_action( 'save_post', [ $this, 'sync_proposal_from_tr' ], 30, 1 );
	}

	/**
	 * Metabox pendaftaran via TypeRocket agar konsisten
	 * dengan Detail Hibah / Detail Program Studi di functions.php.
	 * CPT pendaftaran_hibah sendiri didaftarkan di functions.php
	 * (tr_post_type, biar muncul di scan/theme-builder); di sini
	 * hanya metabox + penyesuaian editor/file.
	 * Fallback register_cpt() tetap menjaga CPT ada bila TR belum load.
	 */
	public function register_tr_cpt_and_metabox(): void {
		if ( ! function_exists( 'tr_meta_box' ) ) { return; }

		// File proposal presisi reset: blank = kosong → true nullify,
		// false = biarkan apa adanya (dipakai sinkron).
		add_filter( 'typerocket_set_null_blank_fields', function ( $fields ) {
			$screen = get_current_screen();
			if ( $screen && 'pendaftaran_hibah' === $screen->post_type ) { $fields[] = '_proposal_id'; }
			return $fields;
		} );

		tr_meta_box( 'Detail Pendaftaran' )
			->addPostType( 'pendaftaran_hibah' )
			->setCallback( [ $this, 'render_tr_metabox' ] );
	}

	public function render_tr_metabox(): void {
		$post_id = (int) get_the_ID();
		$form    = \TypeRocket\Utility\Helper::form();
		$get     = function ( string $k ) use ( $post_id ) {
			$v = get_post_meta( $post_id, $k, true );
			return is_string( $v ) ? $v : (string) $v;
		};

		$reg_no       = $get( '_reg_no' );
		$hibah_id_raw = $get( '_hibah_id' );
		$event_title  = $hibah_id_raw ? get_the_title( (int) $hibah_id_raw ) : '';
		$proposal_url = $get( '_proposal_url' );
		// Normalize _anggota_list: TR repeater stores array, legacy stores JSON string.
		// Migrate JSON → array once so repeater can display existing data.
		$raw_anggota = get_post_meta( $post_id, '_anggota_list', true );
		if ( is_string( $raw_anggota ) && '' !== trim( $raw_anggota ) ) {
			$decoded = json_decode( $raw_anggota, true );
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, '_anggota_list', $decoded );
				$raw_anggota = $decoded;
			}
		}
		$anggota_repeater = $form->repeater( '_anggota_list' )
			->setLabel( 'Anggota Tim (maks 2 Dosen + 2 Mahasiswa)' )
			->setHelp( 'Dosen = NIDN, Mahasiswa = NIM + Prodi. Kosongkan baris yang tidak perlu — akan diabaikan saat simpan.' )
			->setFields( [
				$form->select( 'tipe' )->setLabel( 'Tipe' )->setOptions( [ 'Dosen' => 'dosen', 'Mahasiswa' => 'mahasiswa' ] )->setAttribute( 'style', 'width:100%' ),
				$form->text( 'nomor' )->setLabel( 'NIM / NIDN' )->setAttribute( 'placeholder', 'NIDN / NIM' ),
				$form->text( 'nama' )->setLabel( 'Nama Lengkap' )->setAttribute( 'placeholder', 'Nama anggota' ),
				$form->text( 'prodi' )->setLabel( 'Prodi (khusus Mahasiswa)' )->setAttribute( 'placeholder', 'Prodi' ),
			] );

		$status       = $get( '_status' ) ?: 'submitted';
		$status_labels = [
			'submitted'    => 'Submitted (baru dikirim)',
			'under_review' => 'Under Review (sedang dinilai)',
			'revised'      => 'Revised (revisi)',
			'approved'     => 'Approved (diterima)',
			'rejected'     => 'Rejected (ditolak)',
			'done'         => 'Done (selesai)',
		];
		$status_opts = [];
		foreach ( $status_labels as $k => $v ) { $status_opts[ $v ] = $k; }

		// ── Header ringkas (TypeRocket form) ──
		echo $form->text( '_reg_no' )->setLabel( 'No. Registrasi' )->setAttribute( 'readonly', 'readonly' )->setHelp( $event_title ? 'Event: ' . $event_title : ( $hibah_id_raw ? 'Event ID: ' . $hibah_id_raw : '' ) );
		echo '<div style="margin:.6rem 0;padding:.9rem 1rem;background:#f0f7ff;border-radius:8px;border-left:3px solid #2271b3">'
			. '<p style="margin:0 0 .4rem;font-weight:600">Status Pendaftaran</p>'
			. $form->select( '_status' )->setLabel( '' )->setOptions( $status_opts )->setAttribute( 'style', 'width:100%;max-width:340px' )
			. '<p style="margin:.45rem 0 0;color:#64748b;font-size:.85em">Diubah di `post.php?post=' . $post_id . '&action=edit` maupun dashboard `PendaftaranDetail` (API).</p>'
			. '</div>';

		// ── Identitas pengusul (editable) ──
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.6rem">'
			. '<div>' . $form->text( '_nama' )->setLabel( 'Nama Lengkap & Gelar' ) . '</div>'
			. '<div>' . $form->text( '_nip' )->setLabel( 'NIDN / NIDK' ) . '</div>'
			. '<div>' . $form->select( '_jenis' )->setLabel( 'Jenis Pengusul' )->setOptions( [ 'Dosen' => 'Dosen', 'Mahasiswa' => 'Mahasiswa', 'Tenaga Kependidikan' => 'Tenaga Kependidikan' ] )->setAttribute( 'style', 'width:100%' ) . '</div>'
			. '<div>' . $form->text( '_prodi' )->setLabel( 'Program Studi / Unit Kerja' ) . '</div>'
			. '<div>' . $form->text( '_skema' )->setLabel( 'Model Hibah' ) . '</div>'
			. '<div>' . $form->text( '_jenis_hibah' )->setLabel( 'Jenis Hibah' ) . '</div>'
			. '<div>' . $form->text( '_sdgs' )->setLabel( 'SDGs' ) . '</div>'
			. '<div>' . $form->text( '_kelompok_keahlian' )->setLabel( 'Kelompok Keahlian' ) . '</div>'
			. '</div>';
		echo '<div style="margin-top:1rem">' . $form->text( '_judul' )->setLabel( 'Judul Usulan' )->setAttribute( 'style', 'width:100%' ) . '</div>';
		echo '<div style="margin-top:1rem">' . $form->textarea( '_ringkasan' )->setLabel( 'Ringkasan Usulan' )->setAttribute( 'rows', 4 ) . '</div>';
		echo '<div style="margin-top:1rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px">' . $anggota_repeater . '</div>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem">'
			. '<div>' . $form->text( '_email' )->setLabel( 'Email' ) . '</div>'
			. '<div>' . $form->text( '_hp' )->setLabel( 'WhatsApp' ) . '</div>'
			. '</div>';

		// ── File proposal (TypeRocket file) ──
		echo '<div style="margin-top:1.2rem;padding:1rem;background:#fff;border:1px solid #dbeafe;border-radius:10px">'
			. '<h4 style="margin:0 0 .6rem">📄 File Proposal (PDF)</h4>';
		if ( $proposal_url ) {
			echo '<p style="margin:0 0 .6rem"><a href="' . esc_url( $proposal_url ) . '" target="_blank" rel="noopener">⬇ Download proposal saat ini</a></p>';
		} else {
			echo '<p style="margin:0 0 .6rem;color:#9ca3af"><em>Belum ada file proposal.</em></p>';
		}
		echo $form->file( '_proposal_id' )->setLabel( 'Ganti / Upload Proposal (PDF, max 10 MB)' )->setHelp( 'Kosongkan bila tidak ingin mengganti. Format hanya PDF — validasi `%PDF-` + finfo dijalankan saat simpan.' )
			. '<p style="margin:.5rem 0 0;color:#64748b;font-size:.82em">POST ` /lp2m/v1/hibah/{id}` (dashboard) juga bisa ganti file via `multipart + proposal`.</p>'
			. '</div>';
	}

	public function register_cpt(): void {
		register_post_type( 'pendaftaran_hibah', [
			'label'               => 'Pendaftaran Hibah',
			'labels'              => [
				'name'          => 'Pendaftaran Hibah',
				'singular_name' => 'Pendaftaran Hibah',
				'menu_name'     => 'Pendaftaran Hibah',
				'all_items'     => 'Semua Pendaftaran',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => [ 'title' ],
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'title_placeholder'   => 'Otomatis — jangan edit manual',
		] );
	}

	/* ────────────────────────────────────────────────────────────
	 *  ADMIN LIST COLUMNS (?post_type=pendaftaran_hibah)
	 * ──────────────────────────────────────────────────────────── */

	public function admin_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				// Title bawaan WP (post_title) disembunyikan — diganti kolom Usulan & Tim yang lebih informatif.
				$new['ph_pengusul'] = 'Pengusul';
				$new['ph_hibah']    = 'Hibah';
				$new['ph_usulan']   = 'Usulan & Tim';
				$new['ph_status']   = 'Status';
				$new['ph_aksi']     = 'Aksi';
			}
			$new[ $key ] = $label;
		}
		// Hilangkan title bawaan (auto "[REG] Nama — Judul") yang bikin tabel sempit & judul wrap per-kata.
		if ( isset( $new['title'] ) ) { unset( $new['title'] ); }
		// Bila filter lama masih ada (mis. Screen Options cache), hilangkan sisa kolom per-field.
		foreach ( [ 'ph_reg_no', 'ph_nama', 'ph_nip', 'ph_prodi', 'ph_model', 'ph_jenis_hibah', 'ph_sdgs', 'ph_kk', 'ph_anggota', 'ph_judul', 'ph_kontak' ] as $legacy ) {
			unset( $new[ $legacy ] );
		}
		return $new;
	}

	public function admin_column_content( string $column, int $post_id ): void {
		$meta = function ( $key ) use ( $post_id ) {
			return get_post_meta( $post_id, $key, true );
		};

		switch ( $column ) {
			case 'ph_pengusul':
				$nama  = (string) $meta( '_nama' );
				$nip   = (string) $meta( '_nip' );
				$prodi = (string) $meta( '_prodi' );
				$jenis = (string) $meta( '_jenis' );
				$reg   = (string) $meta( '_reg_no' );
				$hp    = (string) $meta( '_hp' );
				$link  = get_edit_post_link( $post_id );
				if ( $reg ) {
					echo '<code style="font-size:11px;background:#f1f5f9;padding:1px 5px;border-radius:4px">' . esc_html( $reg ) . '</code><br>';
				}
				if ( $nama && $link ) {
					echo '<a href="' . esc_url( $link ) . '"><strong>' . esc_html( $nama ) . '</strong></a>';
				} elseif ( $nama ) {
					echo '<strong>' . esc_html( $nama ) . '</strong>';
				} else {
					echo '<span style="color:#9ca3af">—</span>';
				}
				$meta_line = array_filter( [ $nip, $prodi, $jenis ] );
				if ( $meta_line ) {
					echo '<br><span style="color:#64748b;font-size:12px">' . esc_html( implode( ' · ', $meta_line ) ) . '</span>';
				}
				if ( $hp ) {
					echo '<br><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $hp ) ) . '" style="font-size:12px;color:#0ea5e9;text-decoration:none">📱 ' . esc_html( $hp ) . '</a>';
				}
				break;

			case 'ph_hibah':
				$parts = array_filter( [
					(string) $meta( '_skema' ),
					(string) $meta( '_jenis_hibah' ),
				] );
				if ( $parts ) {
					echo esc_html( implode( ' / ', $parts ) );
				} else {
					echo '<span style="color:#9ca3af">—</span>';
				}
				$extra = array_filter( [
					(string) $meta( '_sdgs' ),
					(string) $meta( '_kelompok_keahlian' ),
				] );
				if ( $extra ) {
					echo '<br><span style="color:#64748b;font-size:12px">' . esc_html( implode( ' · ', $extra ) ) . '</span>';
				}
				break;

			case 'ph_usulan':
				$judul = (string) $meta( '_judul' );
				if ( $judul ) {
					$title = esc_html( mb_strimwidth( $judul, 0, 88, '…' ) );
					echo '<span title="' . esc_attr( $judul ) . '"><strong>' . $title . '</strong></span>';
				} else {
					echo '<span style="color:#9ca3af">Tanpa judul</span>';
				}
				$raw  = $meta( '_anggota_list' );
				$list = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== trim( $raw ) ? ( json_decode( $raw, true ) ?: [] ) : [] );
				if ( ! empty( $list ) ) {
					$lines = [];
					foreach ( $list as $m ) {
						$t   = $m['tipe'] ?? '';
						$nid = $m['nomor'] ?? '';
						$nm  = $m['nama'] ?? '';
						if ( 'mahasiswa' === $t ) {
							$lines[] = sprintf( 'Mhs: %s (%s)', $nm, $nid );
						} else {
							$lines[] = sprintf( 'Dosen: %s (%s)', $nm, $nid );
						}
					}
					$escaped = array_map( 'esc_html', $lines );
					echo '<br><span style="color:#475569;font-size:12px">' . implode( '<br>', $escaped ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sudah esc per-item
				}
				break;

			case 'ph_status':
				$status = (string) $meta( '_status' ) ?: 'submitted';
				$labels = [
					'submitted'    => 'Submitted',
					'under_review' => 'Under Review',
					'revised'      => 'Revised',
					'approved'     => 'Approved',
					'rejected'     => 'Rejected',
					'done'         => 'Done',
				];
				$colors = [
					'submitted'    => '#64748b',
					'under_review' => '#d97706',
					'revised'      => '#0284c7',
					'approved'     => '#16a34a',
					'rejected'     => '#dc2626',
					'done'         => '#7c3aed',
				];
				$label = $labels[ $status ] ?? ucfirst( $status );
				$color = $colors[ $status ] ?? '#64748b';
				echo '<button type="button" class="lp2m-status-badge" data-post="' . (int) $post_id . '" data-status="' . esc_attr( $status ) . '" title="Klik untuk ubah status" style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;background:' . esc_attr( $color ) . ';border:0;cursor:pointer">' . esc_html( $label ) . '</button>';
				echo '<span class="lp2m-status-inline-msg" id="lp2m-status-msg-' . (int) $post_id . '" style="display:block;font-size:10px;margin-top:4px;min-height:12px"></span>';
				break;

			case 'ph_aksi':
				$email   = (string) $meta( '_email' );
				$nonce_s = wp_create_nonce( 'lp2m_inline_status_' . $post_id );
				$nonce_e = wp_create_nonce( 'lp2m_inline_email_' . $post_id );
				echo '<div class="lp2m-aksi" data-post="' . (int) $post_id . '" data-nonce-s="' . esc_attr( $nonce_s ) . '" data-nonce-e="' . esc_attr( $nonce_e ) . '" style="display:flex;flex-direction:column;gap:6px;min-width:175px">';
				echo '<label style="font-size:11px;font-weight:600;color:#475569">Kirim Email ke Pemohon</label>';
				$email_val = esc_attr( $email );
				$placeholder = is_email( $email ) ? '' : 'Email belum ada — isi dulu';
				echo '<input type="email" class="regular-text lp2m-email-input" data-post="' . (int) $post_id . '" value="' . $email_val . '" placeholder="' . esc_attr( $placeholder ) . '" style="width:100%;font-size:12px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px" />';
				echo '<button type="button" class="button button-primary lp2m-btn-email" data-post="' . (int) $post_id . '" style="width:100%;justify-content:center;display:inline-flex;align-items:center;gap:6px;min-height:28px">'
					. '<span class="lp2m-btn-email-label">📧 Kirim Email</span>'
					. '<span class="lp2m-spinner" style="display:none;width:12px;height:12px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:lp2mSpin .6s linear infinite"></span>'
					. '</button>';
				echo '<span class="lp2m-email-msg" id="lp2m-email-msg-' . (int) $post_id . '" style="font-size:11px;min-height:14px;display:block"></span>';
				echo '<span style="font-size:10px;color:#94a3b8">Email di input tidak otomatis disimpan ke data; hanya untuk tujuan kirim. Biarkan default untuk kirim ke pemohon.</span>';
				echo '</div>';
				break;
		}
	}

	/**
	 * Style ringan untuk list pendaftaran agar 5 kolom gabungan lega dan tidak terpotong.
	 * Di list wp-admin `fixed` table memakai width %; tanpa ini Usulan memanjang vertikal per-kata.
	 */
	public function admin_list_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen ) { return; }
		$is_list = 'edit-pendaftaran_hibah' === $screen->id;
		$is_edit = 'pendaftaran_hibah' === $screen->post_type;
		if ( ! $is_list && ! $is_edit ) { return; }
		echo '<style>
			/* List: 5 kolom proporsional, Usulan paling lebar agar judul tidak wrap per-kata */
			.wp-list-table .column-ph_pengusul{width:20%}
			.wp-list-table .column-ph_hibah{width:14%}
			.wp-list-table .column-ph_usulan{width:28%}
			.wp-list-table .column-ph_status{width:11%;text-align:center}
			.wp-list-table .column-ph_aksi{width:27%}
			.wp-list-table .column-ph_usulan{word-break:normal;overflow-wrap:anywhere;white-space:normal}
			.wp-list-table .column-ph_usulan strong{line-height:1.35}
			.wp-list-table .column-ph_aksi .lp2m-aksi input{max-width:100%}
			@keyframes lp2mSpin{to{transform:rotate(360deg)}}
			#lp2mStatusModal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:100050}
			#lp2mStatusModal.lp2m-open{display:flex}
			#lp2mStatusModal .lp2m-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45)}
			#lp2mStatusModal .lp2m-card{position:relative;background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.2);padding:20px;width:min(420px,92vw);z-index:1}
			@media screen and (max-width:782px){
				.wp-list-table .column-ph_pengusul,.wp-list-table .column-ph_hibah,.wp-list-table .column-ph_usulan,.wp-list-table .column-ph_status,.wp-list-table .column-ph_aksi{width:auto}
			}
			/* Edit screen: title input & metabox perlebar */
			#titlediv #title{font-size:1.15em;padding:8px 10px}
			.post-type-pendaftaran_hibah .inside .tr-repeater-row{word-break:normal}
		</style>';
		// Tombol Kirim Email di edit screen + row action di list — tanpa reload halaman tambahan.
		if ( $is_edit && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$email   = (string) get_post_meta( $post_id, '_email', true );
			if ( is_email( $email ) ) {
				$this->render_send_email_ui( $post_id, $email );
			}
		}
		// Modal + JS list: klik status → modal, pilih → ajax; kirim email pakai input override.
		if ( $is_list ) {
			$ajax_url = admin_url( 'admin-ajax.php' );
			echo '<div id="lp2mStatusModal" aria-hidden="true"><div class="lp2m-backdrop"></div><div class="lp2m-card" role="dialog" aria-modal="true" aria-labelledby="lp2mModalTitle">'
				. '<h3 id="lp2mModalTitle" style="margin:0 0 10px;font-size:15px">Update Status</h3>'
				. '<p id="lp2mModalSub" style="margin:0 0 10px;color:#64748b;font-size:12px"></p>'
				. '<select id="lp2mModalSelect" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px">'
				. '<option value="submitted">Submitted</option><option value="under_review">Under Review</option><option value="revised">Revised</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="done">Done</option>'
				. '</select>'
				. '<div id="lp2mModalMsg" style="min-height:16px;margin-top:8px;font-size:12px"></div>'
				. '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">'
				. '<button type="button" class="button" id="lp2mModalCancel">Batal</button>'
				. '<button type="button" class="button button-primary" id="lp2mModalSave" style="min-width:96px;display:inline-flex;align-items:center;justify-content:center;gap:6px"><span class="lp2m-modal-label">Simpan</span><span class="lp2m-spinner" style="display:none;width:12px;height:12px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:lp2mSpin .6s linear infinite"></span></button>'
				. '</div></div></div>';
			echo '<script>document.addEventListener("DOMContentLoaded",function(){'
				. 'var ajaxUrl="' . esc_js( $ajax_url ) . '";'
				. 'var modal=document.getElementById("lp2mStatusModal");var sel=document.getElementById("lp2mModalSelect");var msg=document.getElementById("lp2mModalMsg");var sub=document.getElementById("lp2mModalSub");var btnSave=document.getElementById("lp2mModalSave");var btnCancel=document.getElementById("lp2mModalCancel");'
				. 'var currentPost=null,currentNonce="";'
				. 'function openModal(post,status,nonce,label){currentPost=post;currentNonce=nonce;sel.value=status;sub.textContent="Pendaftaran #"+post+" — "+label;msg.textContent="";msg.style.color="#64748b";modal.classList.add("lp2m-open");modal.setAttribute("aria-hidden","false");}'
				. 'function closeModal(){modal.classList.remove("lp2m-open");modal.setAttribute("aria-hidden","true");currentPost=null;btnSave.disabled=false;btnSave.querySelector(".lp2m-spinner").style.display="none";btnSave.querySelector(".lp2m-modal-label").textContent="Simpan";}'
				. 'if(btnCancel) btnCancel.addEventListener("click",closeModal);'
				. 'if(modal) modal.querySelector(".lp2m-backdrop").addEventListener("click",closeModal);'
				. 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.classList.contains("lp2m-open")) closeModal();});'
				. 'document.querySelectorAll(".lp2m-status-badge").forEach(function(badge){badge.addEventListener("click",function(){var post=badge.getAttribute("data-post");var status=badge.getAttribute("data-status")||"submitted";var wrap=document.querySelector(".lp2m-aksi[data-post=\""+post+"\"]");var nonce=wrap?wrap.getAttribute("data-nonce-s"):"";openModal(post,status,nonce,badge.textContent.trim());});});'
				. 'if(btnSave) btnSave.addEventListener("click",function(){if(!currentPost) return; var status=sel.value; var m=document.getElementById("lp2m-status-msg-"+currentPost); var badge=document.querySelector("tr#post-"+currentPost+" .lp2m-status-badge");'
				. 'msg.textContent="Menyimpan & mengirim email…";msg.style.color="#64748b";btnSave.disabled=true;btnSave.querySelector(".lp2m-spinner").style.display="inline-block";btnSave.querySelector(".lp2m-modal-label").textContent="Menyimpan…"; if(m){m.textContent="Menyimpan…";m.style.color="#64748b";}'
				. 'fetch(ajaxUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=lp2m_inline_status&post="+encodeURIComponent(currentPost)+"&status="+encodeURIComponent(status)+"&_ajax_nonce="+encodeURIComponent(currentNonce)})'
				. '.then(function(r){return r.json()}).then(function(j){var ok=j&&j.success;var text=ok?(j.data&&j.data.message?j.data.message:"Status disimpan & email terkirim."):(j&&j.data&&j.data.message?j.data.message:"Gagal");msg.textContent=(ok?"✓ ":"✕ ")+text;msg.style.color=ok?"#16a34a":"#dc2626";if(m){m.textContent=(ok?"✓ ":"✕ ")+text;m.style.color=ok?"#16a34a":"#dc2626";} if(ok&&j.data&&j.data.status_label&&badge){badge.textContent=j.data.status_label;badge.setAttribute("data-status",status);var colors={submitted:"#64748b",under_review:"#d97706",revised:"#0284c7",approved:"#16a34a",rejected:"#dc2626",done:"#7c3aed"};badge.style.background=colors[status]||"#64748b";} if(ok){setTimeout(closeModal,900);}})'
				. '.catch(function(){msg.textContent="✕ Gagal terhubung.";msg.style.color="#dc2626";if(m){m.textContent="✕ Gagal terhubung.";m.style.color="#dc2626";}}).finally(function(){btnSave.disabled=false;btnSave.querySelector(".lp2m-spinner").style.display="none";btnSave.querySelector(".lp2m-modal-label").textContent="Simpan";});});'
				. 'document.querySelectorAll(".lp2m-btn-email").forEach(function(btn){btn.addEventListener("click",function(){'
				. 'var post=btn.getAttribute("data-post");var wrap=document.querySelector(".lp2m-aksi[data-post=\""+post+"\"]"); if(!wrap) return; var nonce=wrap.getAttribute("data-nonce-e"); var input=wrap.querySelector(".lp2m-email-input"); var emailOverride=input?input.value.trim():"";'
				. 'var m=document.getElementById("lp2m-email-msg-"+post); var label=btn.querySelector(".lp2m-btn-email-label"); var spin=btn.querySelector(".lp2m-spinner");'
				. 'if(m) {m.textContent="Mengirim…";m.style.color="#64748b";} btn.disabled=true; if(spin) spin.style.display="inline-block"; if(label) label.textContent="Mengirim…";'
				. 'var body="action=lp2m_inline_email&post="+encodeURIComponent(post)+"&_ajax_nonce="+encodeURIComponent(nonce); if(emailOverride) body+="&email_override="+encodeURIComponent(emailOverride);'
				. 'fetch(ajaxUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:body})'
				. '.then(function(r){return r.json()}).then(function(j){if(m){var ok=j&&j.success;var t=j&&j.data&&j.data.message?j.data.message:(ok?"Email terkirim.":"Gagal");m.textContent=(ok?"✓ ":"✕ ")+t; m.style.color=ok?"#16a34a":"#dc2626";}})'
				. '.catch(function(){if(m){m.textContent="✕ Gagal terhubung.";m.style.color="#dc2626"}}).finally(function(){btn.disabled=false;if(spin) spin.style.display="none";if(label) label.textContent="📧 Kirim Email";});});});'
				. '});</script>';
		}
	}

	/**
	 * UI tombol Kirim Email di edit screen (admin_head) — form kecil di atas Publish box.
	 */
	private function render_send_email_ui( int $post_id, string $email ): void {
		$nonce = wp_create_nonce( 'lp2m_send_email_' . $post_id );
		$msg   = '';
		if ( isset( $_GET['lp2m_email_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sent = sanitize_text_field( (string) $_GET['lp2m_email_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '1' === $sent ) {
				$msg = '<div class="notice notice-success is-dismissible" style="margin:12px 0"><p>✓ Email terkirim ke ' . esc_html( $email ) . '.</p></div>';
			} elseif ( '0' === $sent ) {
				$err = isset( $_GET['lp2m_email_err'] ) ? sanitize_text_field( (string) $_GET['lp2m_email_err'] ) : 'Gagal mengirim email.'; // phpcs:ignore
				$msg = '<div class="notice notice-error is-dismissible" style="margin:12px 0"><p>✕ ' . esc_html( $err ) . '</p></div>';
			}
		}
		echo $msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — notice sudah esc
		// Inject tombol via JS agar muncul di publish box tanpa override template.
		echo '<script>document.addEventListener("DOMContentLoaded",function(){'
			. 'var box=document.getElementById("submitdiv"); if(!box) return;'
			. 'var wrap=document.createElement("div"); wrap.style.cssText="margin:12px 0 0;padding:10px 12px;background:#f0f7ff;border:1px solid #dbeafe;border-radius:8px";'
			. 'wrap.innerHTML=\'<p style="margin:0 0 6px;font-weight:600">📧 Kirim Email ke Pemohon</p>'
			. '<p style="margin:0 0 8px;color:#475569;font-size:12px">Kirim data terbaru (sesuai edit terakhir) ke <strong>' . esc_js( $email ) . '</strong>.</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'post.php' ) ) . '" style="margin:0">'
			. '<input type="hidden" name="action" value="lp2m_send_email">'
			. '<input type="hidden" name="post" value="' . (int) $post_id . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">'
			. '<label style="display:block;margin-bottom:4px;font-size:12px">Perihal / catatan (opsional)</label>'
			. '<input type="text" name="lp2m_subject_note" placeholder="mis. Revisi disetujui / mohon lengkapi berkas" style="width:100%;margin-bottom:8px">'
			. '<button type="submit" class="button button-primary" style="width:100%">Kirim Email Sekarang</button>'
			. '</form>\';'
			. 'var h=box.querySelector(".inside"); if(h) h.prepend(wrap); else box.prepend(wrap);});</script>';
	}

	/**
	 * Handler post_action_lp2m_send_email (dari post.php edit screen).
	 */
	public function handle_admin_send_email( int $post_id ): void {
		if ( ! current_user_can( 'edit_post', $post_id ) ) { wp_die( 'Tidak diizinkan.' ); }
		check_admin_referer( 'lp2m_send_email_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) { wp_die( 'Data tidak ditemukan.' ); }
		$email = (string) get_post_meta( $post_id, '_email', true );
		if ( ! is_email( $email ) ) {
			wp_redirect( add_query_arg( [ 'lp2m_email_sent' => '0', 'lp2m_email_err' => rawurlencode( 'Email pemohon tidak valid.' ) ], get_edit_post_link( $post_id, '' ) ) ); exit;
		}
		$note = isset( $_POST['lp2m_subject_note'] ) ? sanitize_text_field( (string) $_POST['lp2m_subject_note'] ) : ''; // phpcs:ignore
		$ok   = $this->send_applicant_email( $post_id, $note );
		if ( is_wp_error( $ok ) ) {
			wp_redirect( add_query_arg( [ 'lp2m_email_sent' => '0', 'lp2m_email_err' => rawurlencode( $ok->get_error_message() ) ], get_edit_post_link( $post_id, '' ) ) ); exit;
		}
		wp_redirect( add_query_arg( [ 'lp2m_email_sent' => '1' ], get_edit_post_link( $post_id, '' ) ) ); exit;
	}

	/**
	 * Handler admin_post (fallback dari list bulk / direct link).
	 */
	public function handle_admin_send_email_list(): void {
		$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0; // phpcs:ignore
		if ( ! $post_id ) { wp_die( 'ID tidak valid.' ); }
		$this->handle_admin_send_email( $post_id );
	}

	/* ── AJAX inline di list wp-admin (fetch tanpa reload) ── */

	public function ajax_inline_status(): void {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $post_id ) { wp_send_json_error( [ 'message' => 'ID tidak valid.' ] ); }
		check_ajax_referer( 'lp2m_inline_status_' . $post_id, '_ajax_nonce' );
		if ( ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( [ 'message' => 'Tidak diizinkan.' ] ); }
		$post = get_post( $post_id );
		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) { wp_send_json_error( [ 'message' => 'Data tidak ditemukan.' ] ); }
		$status = isset( $_POST['status'] ) ? sanitize_text_field( (string) $_POST['status'] ) : ''; // phpcs:ignore
		if ( ! in_array( $status, self::STATUSES, true ) ) { wp_send_json_error( [ 'message' => 'Status tidak valid.' ] ); }
		$old = (string) get_post_meta( $post_id, '_status', true ) ?: 'submitted';
		update_post_meta( $post_id, '_status', $status );
		$labels = [ 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'revised' => 'Revised', 'approved' => 'Approved', 'rejected' => 'Rejected', 'done' => 'Done' ];
		$label  = $labels[ $status ] ?? ucfirst( $status );
		// Setiap update status → kirim email ke pemohon (sesuai permintaan).
		if ( $old !== $status ) {
			$note = 'Status diperbarui: ' . $label;
			$res  = $this->send_applicant_email( $post_id, $note );
			if ( is_wp_error( $res ) ) {
				wp_send_json_success( [ 'message' => 'Status disimpan, tapi email gagal: ' . $res->get_error_message(), 'status' => $status, 'status_label' => $label, 'email_sent' => false ] );
				return;
			}
		} else {
			// Status sama — tetap kirim notifikasi (user sengaja menekan Simpan).
			$res = $this->send_applicant_email( $post_id, 'Status: ' . $label );
			if ( is_wp_error( $res ) ) {
				wp_send_json_success( [ 'message' => 'Email gagal: ' . $res->get_error_message(), 'status' => $status, 'status_label' => $label, 'email_sent' => false ] );
				return;
			}
		}
		wp_send_json_success( [ 'message' => '✓ Status ' . $label . ' & email terkirim.', 'status' => $status, 'status_label' => $label, 'email_sent' => true ] );
	}

	public function ajax_inline_email(): void {
		$post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $post_id ) { wp_send_json_error( [ 'message' => 'ID tidak valid.' ] ); }
		check_ajax_referer( 'lp2m_inline_email_' . $post_id, '_ajax_nonce' );
		if ( ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( [ 'message' => 'Tidak diizinkan.' ] ); }
		$post = get_post( $post_id );
		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) { wp_send_json_error( [ 'message' => 'Data tidak ditemukan.' ] ); }
		$note = isset( $_POST['note'] ) ? sanitize_text_field( (string) $_POST['note'] ) : ''; // phpcs:ignore
		$override = isset( $_POST['email_override'] ) ? sanitize_email( (string) $_POST['email_override'] ) : ''; // phpcs:ignore
		$res  = $this->send_applicant_email( $post_id, $note, $override ?: null );
		if ( is_wp_error( $res ) ) { wp_send_json_error( [ 'message' => $res->get_error_message() ] ); }
		wp_send_json_success( [ 'message' => '✓ Email terkirim ke ' . ( $override ?: (string) get_post_meta( $post_id, '_email', true ) ) . '.' ] );
	}

	/**
	 * REST: kirim email manual ke pemohon (dipakai dashboard LP2M).
	 * Body JSON: { note?, email? } — email = override tujuan kirim saja, tidak disimpan.
	 */
	public function handle_rest_send_email( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );
		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}
		$note = sanitize_text_field( (string) ( $request->get_param( 'note' ) ?? $request->get_param( 'subject_note' ) ?? '' ) );
		$override = sanitize_email( (string) ( $request->get_param( 'email' ) ?? $request->get_param( 'email_override' ) ?? '' ) );
		$res  = $this->send_applicant_email( $id, $note, $override ?: null );
		if ( is_wp_error( $res ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => $res->get_error_message() ], 500 );
		}
		return new \WP_REST_Response( [ 'success' => true, 'message' => 'Email terkirim.' ], 200 );
	}

	/**
	 * Kirim email ke pemohon dengan data terbaru dari postmeta.
	 * Dipakai tombol wp-admin maupun API manual.
	 * $email_override: bila diisi, dipakai sebagai tujuan kirim saja (tidak disimpan ke _email).
	 *
	 * @return true|\WP_Error
	 */
	public function send_applicant_email( int $post_id, string $subject_note = '', ?string $email_override = null ): bool|\WP_Error {
		$post = get_post( $post_id );
		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_Error( 'not_found', 'Data pendaftaran tidak ditemukan.' );
		}
		$stored = (string) get_post_meta( $post_id, '_email', true );
		$email  = $email_override !== null && '' !== trim( $email_override ) ? sanitize_email( $email_override ) : $stored;
		if ( null !== $email_override && '' !== trim( (string) $email_override ) && ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Email tujuan tidak valid.' );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Email pemohon tidak valid (isi di Detail Pendaftaran / kolom Email).' );
		}
		$reg_no = (string) get_post_meta( $post_id, '_reg_no', true );
		$hibah_id = (int) get_post_meta( $post_id, '_hibah_id', true );
		$event_name = $hibah_id ? (string) get_the_title( $hibah_id ) : '';
		$raw_list = get_post_meta( $post_id, '_anggota_list', true );
		$anggota_list = is_array( $raw_list ) ? $raw_list : ( is_string( $raw_list ) && '' !== trim( $raw_list ) ? ( json_decode( $raw_list, true ) ?: [] ) : [] );
		$params = [
			'nama' => (string) get_post_meta( $post_id, '_nama', true ),
			'nip'  => (string) get_post_meta( $post_id, '_nip', true ),
			'jenis' => (string) get_post_meta( $post_id, '_jenis', true ),
			'prodi' => (string) get_post_meta( $post_id, '_prodi', true ),
			'skema' => (string) get_post_meta( $post_id, '_skema', true ),
			'jenis_hibah' => (string) get_post_meta( $post_id, '_jenis_hibah', true ),
			'sdgs'  => (string) get_post_meta( $post_id, '_sdgs', true ),
			'kelompok_keahlian' => (string) get_post_meta( $post_id, '_kelompok_keahlian', true ),
			'judul' => (string) get_post_meta( $post_id, '_judul', true ),
			'ringkasan' => (string) get_post_meta( $post_id, '_ringkasan', true ),
			'anggota_list' => $anggota_list,
			'email' => $email,
			'hp'    => (string) get_post_meta( $post_id, '_hp', true ),
		];
		$status = (string) get_post_meta( $post_id, '_status', true ) ?: 'submitted';
		$subject = sprintf( '[LP2M] %s — %s', $reg_no ?: ( 'Pendaftaran #' . $post_id ), $subject_note ? $subject_note : ( 'Status: ' . ucfirst( $status ) ) );
		$body = $this->email_html( $params, $reg_no ?: (string) $post_id, $event_name, '' );
		if ( '' !== trim( $subject_note ) ) {
			$body = '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:16px;color:#92400e;font-size:13px"><strong>Catatan:</strong> ' . esc_html( $subject_note ) . '</div>' . $body;
		}
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$sent = wp_mail( $email, $subject, $body, $headers );
		if ( ! $sent ) {
			return new \WP_Error( 'mail_failed', 'Gagal mengirim email. Periksa konfigurasi SMTP di LP2M → Settings.' );
		}
		return true;
	}

	public function register_routes(): void {
		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_submit' ],
			'permission_callback' => [ $this, 'check_rate_limit' ],
		] );

		register_rest_route( 'lp2m/v1', '/hibah', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_list' ],
			'permission_callback' => [ $this, 'check_rate_limit_read' ],
		] );

		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_detail' ],
			'permission_callback' => [ $this, 'check_rate_limit_read' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Konfigurasi form publik: program studi (CPT) + skema (taxonomy hierarchical).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)/form-config', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_form_config' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Update status / data pendaftaran (admin, via Basic auth).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_update' ],
			'permission_callback' => [ $this, 'check_admin' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Hapus pendaftaran (admin, via Basic auth).
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'handle_delete' ],
			'permission_callback' => [ $this, 'check_admin' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Kirim email manual ke pemohon (admin, via Basic auth) — dipakai dashboard LP2M + tabel wp-admin via fetch.
		register_rest_route( 'lp2m/v1', '/hibah/(?P<id>\d+)/email', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_rest_send_email' ],
			'permission_callback' => [ $this, 'check_admin' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && (int) $param > 0;
					},
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Statistik infografis: agregasi pendaftaran per tahun (publik, untuk homepage + dashboard).
		register_rest_route( 'lp2m/v1', '/statistik', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_statistik' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'tahun' => [
					'required'          => false,
					'validate_callback' => function ( $param ) {
						return '' === (string) $param || (bool) preg_match( '/^\d{4}$/', (string) $param );
					},
					'sanitize_callback' => function ( $param ) {
						return '' === (string) $param ? '' : (string) $param;
					},
				],
			],
		] );
	}

	/**
	 * Permission: editor ke atas (edit_posts) — admin tetap lolos.
	 * Basic auth diterima untuk Application Password (core) maupun password
	 * akun (fallback lp2m-auth.php). Sebelumnya hanya `manage_options`
	 * sehingga akun LP2M role editor tidak bisa update pendaftaran (403).
	 */
	public function check_admin(): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
			return true;
		}
		return new \WP_Error( 'forbidden', 'Anda tidak memiliki akses.', [ 'status' => 403 ] );
	}

	/**
	 * Form config: daftar program studi (CPT) + skema (taxonomy, parent-child+desc).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_form_config( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( ! get_post( $id ) || 'hibah' !== get_post_type( $id ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Hibah tidak ditemukan.' ], 404 );
		}

		$cache_key = 'lp2m_form_config_' . $id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			$response = new \WP_REST_Response( $cached, 200 );
			$response->header( 'Cache-Control', 'public, max-age=600, stale-while-revalidate=60' );
			$response->header( 'X-LP2M-Cache', 'HIT' );
			return $response;
		}

		// Program studi: CPT program_studi → id + nama.
		$prodi_posts = get_posts( [
			'post_type'      => 'program_studi',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		$prodi_options = [];
		foreach ( $prodi_posts as $p ) {
			$prodi_options[] = [ 'id' => $p->ID, 'name' => get_the_title( $p ) ];
		}

		// Skema: taxonomy model_hibah hierarchical → flatten parent-child + desc.
		// (Legacy: kalau model_hibah belum ada, fallback ke skema_hibah.)
		$skema_tax = taxonomy_exists( 'model_hibah' ) ? 'model_hibah' : 'skema_hibah';
		$skema_terms = get_terms( [
			'taxonomy'   => $skema_tax,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		$skema_options = [];
		if ( ! is_wp_error( $skema_terms ) ) {
			$by_id = [];
			foreach ( $skema_terms as $t ) {
				$by_id[ $t->term_id ] = $t;
			}
			foreach ( $skema_terms as $t ) {
				$label = $t->name;
				$parent = '';
				if ( $t->parent && isset( $by_id[ $t->parent ] ) ) {
					$label  = $by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $by_id[ $t->parent ]->name;
				}
				$skema_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
			}
		}

		// Jenis hibah: taxonomy jenis_hibah (hierarchical → flatten parent-child).
		$jenis_options = [];
		$jenis_terms   = get_terms( [
			'taxonomy'   => 'jenis_hibah',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $jenis_terms ) ) {
			$jenis_by_id = [];
			foreach ( $jenis_terms as $t ) {
				$jenis_by_id[ $t->term_id ] = $t;
			}
			foreach ( $jenis_terms as $t ) {
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$label  = $t->name;
				$parent = '';
				if ( $t->parent && isset( $jenis_by_id[ $t->parent ] ) ) {
					$label  = $jenis_by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $jenis_by_id[ $t->parent ]->name;
				}
				$jenis_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
			}
		}

		// SDGs: taxonomy sdgs.
		$sdgs_options = [];
		$sdgs_terms   = get_terms( [
			'taxonomy'   => 'sdgs',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $sdgs_terms ) ) {
			foreach ( $sdgs_terms as $t ) {
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$sdgs_options[] = [ 'id' => $t->term_id, 'name' => $t->name ];
			}
		}

		// Kelompok keahlian: taxonomy kelompok_keahlian (hierarchical → flatten parent-child).
		$kk_options = [];
		$kk_terms   = get_terms( [
			'taxonomy'   => 'kelompok_keahlian',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( ! is_wp_error( $kk_terms ) ) {
			$kk_by_id = [];
			foreach ( $kk_terms as $t ) {
				$kk_by_id[ $t->term_id ] = $t;
			}
			foreach ( $kk_terms as $t ) {
				if ( '' === trim( (string) $t->name ) ) { continue; }
				$label  = $t->name;
				$parent = '';
				if ( $t->parent && isset( $kk_by_id[ $t->parent ] ) ) {
					$label  = $kk_by_id[ $t->parent ]->name . ' — ' . $t->name;
					$parent = $kk_by_id[ $t->parent ]->name;
				}
				$kk_options[] = [
					'id'     => $t->term_id,
					'label'  => $label,
					'name'   => $t->name,
					'parent' => $parent,
					'desc'   => $t->description,
				];
			}
		}

		$data = [
			'success'          => true,
			'hibah_id'         => $id,
			// Boleh daftar setelah deadline — nilai efektif (per-event → fallback global).
			'allow_after_deadline' => itsi_hibah_allow_after_deadline( $id ),
			'prodi_options'    => $prodi_options,
			'skema_options'    => $skema_options,
			'jenis_options'    => $jenis_options,
			'sdgs_options'     => $sdgs_options,
			'kk_options'       => $kk_options,
			'kelompok_options' => $kk_options,
		];
		set_transient( $cache_key, $data, 10 * MINUTE_IN_SECONDS );

		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'public, max-age=600, stale-while-revalidate=60' );
		$response->header( 'X-LP2M-Cache', 'MISS' );
		return $response;
	}

	/* ────────────────────────────────────────────────────────────
	 *  RATE LIMITING
	 * ──────────────────────────────────────────────────────────── */

	public function check_rate_limit( \WP_REST_Request $request ): bool|\WP_Error {
		if ( 'GET' === $request->get_method() ) {
			return true;
		}

		$ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key   = 'lp2m_hibah_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 5 ) {
			return new \WP_Error(
				'rate_limit',
				'Terlalu banyak permintaan. Silakan coba lagi dalam 15 menit.',
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Rate limit untuk endpoint publik GET (statistik, list, detail, form-config).
	 * 120 permintaan / 10 menit per IP — cukup untuk halaman normal, melindungi dari scraping.
	 */
	public function check_rate_limit_read( \WP_REST_Request $request ): bool|\WP_Error {
		$ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$key   = 'lp2m_hibah_rate_read_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 120 ) {
			return new \WP_Error(
				'rate_limit_read',
				'Terlalu banyak permintaan. Silakan coba lagi dalam 10 menit.',
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/* ────────────────────────────────────────────────────────────
	 *  SANITIZATION
	 * ──────────────────────────────────────────────────────────── */

	private function sanitize_input( array $params ): array {
		$allowed = [
			'hibah_id', 'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul',
			'ringkasan', 'jml_tim', 'anggota', 'email', 'hp',
			'skema_id', 'prodi_id',
			'jenis_hibah', 'jenis_hibah_id', 'sdgs', 'sdgs_id', 'kelompok_keahlian', 'kk_id',
		];

		$clean = [];
		foreach ( $allowed as $field ) {
			$val = $params[ $field ] ?? '';
			if ( is_array( $val ) || is_object( $val ) ) { $val = ''; }
			$clean[ $field ] = is_string( $val ) ? trim( $val ) : (string) $val;
		}

		$clean['hibah_id'] = (string) absint( $clean['hibah_id'] );
		$clean['skema_id'] = (string) absint( $clean['skema_id'] );
		$clean['prodi_id'] = (string) absint( $clean['prodi_id'] );
		$clean['jenis_hibah_id'] = (string) absint( $clean['jenis_hibah_id'] );
		$clean['sdgs_id'] = (string) absint( $clean['sdgs_id'] );
		$clean['kk_id'] = (string) absint( $clean['kk_id'] );

		$jenis_whitelist = [ 'Dosen', 'Mahasiswa', 'Tenaga Kependidikan' ];
		if ( ! in_array( $clean['jenis'], $jenis_whitelist, true ) ) {
			$clean['jenis'] = '';
		}

		if ( $clean['jml_tim'] !== '' ) {
			$clean['jml_tim'] = (string) absint( $clean['jml_tim'] );
		}

		$clean['nip']   = preg_replace( '/[^a-zA-Z0-9\-\\.]/', '', $clean['nip'] );
		$clean['hp']    = preg_replace( '/[^0-9\+\-]/', '', $clean['hp'] );
		$clean['email'] = sanitize_email( $clean['email'] );

		$text_fields = [ 'nama', 'prodi', 'skema', 'judul', 'ringkasan', 'anggota', 'jenis_hibah', 'sdgs', 'kelompok_keahlian' ];
		foreach ( $text_fields as $f ) {
			$clean[ $f ] = wp_strip_all_tags( $clean[ $f ], true );
		}

		$clean['anggota'] = mb_substr( $clean['anggota'], 0, 500 );

		// Anggota tim dinamis: array [{tipe, nomor, nama, prodi}] — max 2 dosen + 2 mahasiswa.
		$clean['anggota_list'] = [];
		$raw_list = $params['anggota_list'] ?? [];
		// Dashboard may send JSON string via FormData — decode first.
		if ( is_string( $raw_list ) && '' !== trim( $raw_list ) ) {
			$decoded = json_decode( $raw_list, true );
			if ( is_array( $decoded ) ) { $raw_list = $decoded; }
		}
		$dosen_count = 0;
		$mhs_count   = 0;
		if ( is_array( $raw_list ) ) {
			foreach ( $raw_list as $m ) {
				if ( ! is_array( $m ) ) { continue; }
				$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'mahasiswa' : 'dosen';
				if ( 'mahasiswa' === $tipe ) {
					if ( $mhs_count >= 2 ) { continue; }
					$mhs_count++;
				} else {
					if ( $dosen_count >= 2 ) { continue; }
					$dosen_count++;
				}
				$nomor = wp_strip_all_tags( (string) ( $m['nomor'] ?? '' ), true );
				$nama  = wp_strip_all_tags( (string) ( $m['nama'] ?? '' ), true );
				$prodi = wp_strip_all_tags( (string) ( $m['prodi'] ?? '' ), true );
				if ( '' === trim( $nomor ) && '' === trim( $nama ) ) { continue; }
				$clean['anggota_list'][] = [
					'tipe'  => $tipe,
					'nomor' => mb_substr( preg_replace( '/[^a-zA-Z0-9\-\\.]/', '', $nomor ), 0, 40 ),
					'nama'  => mb_substr( $nama, 0, 150 ),
					'prodi' => mb_substr( $prodi, 0, 150 ),
				];
			}
		}

		return $clean;
	}

	/* ────────────────────────────────────────────────────────────
	 *  VALIDATION
	 * ──────────────────────────────────────────────────────────── */

	private function validate( array $params ): array {
		$errors = [];

		$labels = [
			'nama' => 'Nama Lengkap & Gelar', 'nip' => 'NIDN / NIDK',
			'judul' => 'Judul Usulan', 'ringkasan' => 'Ringkasan Usulan',
			'email' => 'Email Aktif', 'hp' => 'Nomor WhatsApp',
		];

		foreach ( $labels as $field => $label ) {
			if ( '' === trim( $params[ $field ] ) ) {
				$errors[ $field ] = $label . ' wajib diisi.';
			}
		}

		if ( '' === trim( $params['jenis'] ) ) { $errors['jenis'] = 'Jenis Pengusul wajib dipilih.'; }
		if ( '' === trim( $params['prodi'] ) ) { $errors['prodi'] = 'Program Studi / Unit Kerja wajib dipilih.'; }
		if ( '' === trim( $params['skema'] ) ) { $errors['skema'] = 'Model Hibah wajib dipilih.'; }

		if ( '' !== $params['email'] && ! is_email( $params['email'] ) ) {
			$errors['email'] = 'Format email tidak valid.';
		}

		$hp_digits = preg_replace( '/[^0-9]/', '', $params['hp'] );
		if ( strlen( $hp_digits ) < 10 ) {
			$errors['hp'] = 'Nomor WhatsApp minimal 10 digit.';
		}

		// Anggota tim dinamis (max 2 dosen + 2 mahasiswa): tiap entry butuh nomor + nama lengkap.
		foreach ( $params['anggota_list'] as $i => $m ) {
			if ( '' === trim( (string) $m['nomor'] ) || '' === trim( (string) $m['nama'] ) ) {
				$errors[ 'anggota_list_' . $i ] = 'Nomor & nama anggota #' . ( $i + 1 ) . ' wajib diisi.';
			}
		}

		$hibah_id = (int) $params['hibah_id'];
		if ( $hibah_id > 0 ) {
			$hibah_post = get_post( $hibah_id );
			if ( ! $hibah_post || 'hibah' !== $hibah_post->post_type || 'publish' !== $hibah_post->post_status ) {
				$errors['hibah_id'] = 'Event hibah tidak ditemukan atau sudah tidak aktif.';
			}
		}

		return $errors;
	}

	/* ────────────────────────────────────────────────────────────
	 *  HANDLERS
	 * ──────────────────────────────────────────────────────────── */

	public function handle_submit( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $this->sanitize_input( $request->get_params() );

		if ( empty( $params['hibah_id'] ) || '0' === $params['hibah_id'] ) {
			$params['hibah_id'] = (string) $this->get_latest_active_hibah_id();
		}

		$errors = $this->validate( $params );

		// Deadline gate — pendaftaran hanya boleh sebelum deadline event hibah,
		// KECUALI pengaturan "izinkan setelah deadline" aktif (per-event di
		// metabox hibah, atau global di LP2M → Settings → Site).
		$hibah_id = (int) $params['hibah_id'];
		if ( $hibah_id > 0 && ! itsi_hibah_allow_after_deadline( $hibah_id ) ) {
			$deadline = (string) get_post_meta( $hibah_id, 'deadline', true );
			if ( '' !== trim( $deadline ) ) {
				$ts = strtotime( $deadline );
				if ( false !== $ts && $ts < time() ) {
					$errors['deadline'] = 'Pendaftaran telah ditutup.';
				}
			}
		}

		// File proposal (multipart/form-data) — validasi PDF + ukuran.
		$proposal_file = $request->get_file_params()['proposal'] ?? null;
		if ( is_array( $proposal_file ) && isset( $proposal_file['error'] ) && UPLOAD_ERR_OK === (int) $proposal_file['error'] ) {
			if ( ! empty( $proposal_file['type'] ) && 'application/pdf' !== $proposal_file['type'] ) {
				$errors['proposal'] = 'Hanya file PDF yang diperbolehkan.';
			} elseif ( ! empty( $proposal_file['size'] ) && $proposal_file['size'] > 10 * 1024 * 1024 ) {
				$errors['proposal'] = 'Ukuran file maksimal 10MB.';
			}
		} else {
			$errors['proposal'] = 'File proposal (PDF) wajib diunggah.';
		}

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [ 'success' => false, 'errors' => $errors ], 400 );
		}

		$hibah_id = (int) $params['hibah_id'];
		$reg_no   = $this->generate_reg_no();
		$post_id  = $this->save_submission( $params, $reg_no, $hibah_id );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menyimpan data.' ], 500 );
		}

		// Simpan file proposal sebagai attachment WP + meta _proposal_id/_proposal_url.
		if ( is_array( $proposal_file ) && isset( $proposal_file['error'] ) && UPLOAD_ERR_OK === (int) $proposal_file['error'] ) {
			$att_id = $this->upload_proposal( $proposal_file, $reg_no );
			if ( is_wp_error( $att_id ) ) {
				wp_delete_post( $post_id, true );
				return new \WP_REST_Response( [ 'success' => false, 'errors' => [ 'proposal' => $att_id->get_error_message() ] ], 400 );
			}
			update_post_meta( $post_id, '_proposal_id', $att_id );
			update_post_meta( $post_id, '_proposal_url', wp_get_attachment_url( $att_id ) );
		}

		$this->last_post_id = (int) $post_id;
		$this->send_admin_email( $params, $reg_no, $hibah_id );

		return new \WP_REST_Response( [
			'success' => true,
			'reg_no'  => $reg_no,
			'message' => 'Pendaftaran dikirim. No. registrasi: ' . $reg_no,
		], 201 );
	}

	/**
	 * Simpan file proposal (PDF) ke media library WP.
	 *
	 * @param array  $file   Entry $_FILES['proposal'] (name, type, tmp_name, error, size).
	 * @param string $reg_no Nomor registrasi (untuk penamaan file).
	 * @return int|\WP_Error Attachment ID.
	 */
	private function upload_proposal( array $file, string $reg_no ): int|\WP_Error {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// ── Validasi isi file (jangan percaya nama file / header MIME klien) ──
		$tmp = $file['tmp_name'] ?? '';
		if ( '' === $tmp || ! is_string( $tmp ) || ! is_file( $tmp ) ) {
			return new \WP_Error( 'upload_invalid', 'File proposal tidak valid.' );
		}
		if ( ! is_readable( $tmp ) ) {
			return new \WP_Error( 'upload_unreadable', 'File proposal tidak dapat dibaca.' );
		}

		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > 10 * 1024 * 1024 ) {
			return new \WP_Error( 'upload_too_large', 'Ukuran file maksimal 10MB.' );
		}

		// Magic bytes PDF: "%PDF-" di 5 byte pertama.
		$head = (string) file_get_contents( $tmp, false, null, 0, 5 );
		if ( '%PDF-' !== $head ) {
			return new \WP_Error( 'upload_not_pdf', 'File harus berupa PDF asli (bukan file lain yang diubah ekstensinya).' );
		}

		// Deteksi MIME asli via finfo (jika tersedia) sebagai lapisan kedua.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $tmp );
			finfo_close( $finfo );
			if ( 'application/pdf' !== $mime_type ) {
				return new \WP_Error( 'upload_not_pdf', 'File harus berupa PDF asli.' );
			}
		}

		// Ekstensi dipaksa .pdf — nama file asli tidak dipercaya.
		$base = sanitize_file_name( sprintf( 'proposal-%s.pdf', $reg_no ) );

		$overrides = [
			'test_form' => false,
			'test_type' => true,
			'mimes'     => [ 'pdf' => 'application/pdf' ],
		];
		$moved = wp_handle_upload( $file, $overrides );
		if ( is_wp_error( $moved ) ) {
			return $moved;
		}

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $moved['type'],
			'post_title'     => 'Proposal ' . $reg_no,
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $moved['file'], 0 );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new \WP_Error( 'upload_failed', 'Gagal menyimpan file proposal.' );
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $moved['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		return $attachment_id;
	}

	public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = max( 1, min( (int) ( $request->get_param( 'per_page' ) ?? 20 ), 100 ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );

		$args = [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$hibah_id = (int) ( $request->get_param( 'hibah_id' ) ?? 0 );
		if ( $hibah_id > 0 ) {
			$args['post_parent'] = $hibah_id;
		}

		$query = new \WP_Query( $args );
		$items = [];

		foreach ( $query->posts as $post ) {
			$status = (string) get_post_meta( $post->ID, '_status', true );
			if ( '' === $status ) {
				$status = 'submitted';
			}
			$raw_list     = get_post_meta( $post->ID, '_anggota_list', true );
			$anggota_list = is_array( $raw_list ) ? $raw_list : ( is_string( $raw_list ) && '' !== trim( $raw_list ) ? ( json_decode( $raw_list, true ) ?: [] ) : [] );
			$items[] = [
				'id'         => $post->ID,
				'reg_no'     => get_post_meta( $post->ID, '_reg_no', true ),
				'hibah_id'   => get_post_meta( $post->ID, '_hibah_id', true ),
				'nama'       => get_post_meta( $post->ID, '_nama', true ),
				'nip'        => get_post_meta( $post->ID, '_nip', true ),
				'jenis'      => get_post_meta( $post->ID, '_jenis', true ),
				'prodi'      => get_post_meta( $post->ID, '_prodi', true ),
				'skema'      => get_post_meta( $post->ID, '_skema', true ),
				'jenis_hibah' => get_post_meta( $post->ID, '_jenis_hibah', true ),
				'sdgs'       => get_post_meta( $post->ID, '_sdgs', true ),
				'kelompok_keahlian' => get_post_meta( $post->ID, '_kelompok_keahlian', true ),
				'judul'      => get_post_meta( $post->ID, '_judul', true ),
				'email'      => get_post_meta( $post->ID, '_email', true ),
				'hp'         => get_post_meta( $post->ID, '_hp', true ),
				'status'     => $status,
				'anggota_list' => $anggota_list,
				'proposal_id'  => get_post_meta( $post->ID, '_proposal_id', true ),
				'proposal_url' => get_post_meta( $post->ID, '_proposal_url', true ),
				'created_at' => $post->post_date,
			];
		}

		return new \WP_REST_Response( [
			'success'     => true,
			'data'        => $items,
			'total'       => (int) $query->found_posts,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $query->found_posts / $per_page ),
		], 200 );
	}

	public function handle_detail( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		$raw_anggota  = get_post_meta( $post->ID, '_anggota_list', true );
		$anggota_list = is_array( $raw_anggota ) ? $raw_anggota : ( is_string( $raw_anggota ) && '' !== trim( $raw_anggota ) ? ( json_decode( $raw_anggota, true ) ?: [] ) : [] );

		// Jumlah tim: kosong pada data lama → hitung dari anggota + 1 (ketua).
		$jml_tim = (string) get_post_meta( $post->ID, '_jml_tim', true );
		if ( '' === trim( $jml_tim ) || '0' === trim( $jml_tim ) ) {
			$jml_tim = (string) ( count( $anggota_list ) + 1 );
		}

		return new \WP_REST_Response( [ 'success' => true, 'data' => [
			'id'         => $post->ID,
			'reg_no'     => get_post_meta( $post->ID, '_reg_no', true ),
			'hibah_id'   => get_post_meta( $post->ID, '_hibah_id', true ),
			'nama'       => get_post_meta( $post->ID, '_nama', true ),
			'nip'        => get_post_meta( $post->ID, '_nip', true ),
			'jenis'      => get_post_meta( $post->ID, '_jenis', true ),
			'prodi'      => get_post_meta( $post->ID, '_prodi', true ),
			'skema'      => get_post_meta( $post->ID, '_skema', true ),
			'jenis_hibah' => get_post_meta( $post->ID, '_jenis_hibah', true ),
			'sdgs'       => get_post_meta( $post->ID, '_sdgs', true ),
			'kelompok_keahlian' => get_post_meta( $post->ID, '_kelompok_keahlian', true ),
			'judul'      => get_post_meta( $post->ID, '_judul', true ),
			'ringkasan'  => get_post_meta( $post->ID, '_ringkasan', true ),
			'jml_tim'    => $jml_tim,
			'anggota'    => get_post_meta( $post->ID, '_anggota', true ),
			'anggota_list' => $anggota_list,
			'email'      => get_post_meta( $post->ID, '_email', true ),
			'hp'         => get_post_meta( $post->ID, '_hp', true ),
			'status'     => (string) ( get_post_meta( $post->ID, '_status', true ) ?: 'submitted' ),
			'proposal_id'  => get_post_meta( $post->ID, '_proposal_id', true ),
			'proposal_url' => get_post_meta( $post->ID, '_proposal_url', true ),
			'created_at' => $post->post_date,
		] ], 200 );
	}

	/**
	 * Update status + data pendaftaran (admin).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_update( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		$params = $request->get_params();

		// Status: whitelist — setiap update status otomatis kirim email ke pemohon.
		$old_status       = (string) get_post_meta( $id, '_status', true ) ?: 'submitted';
		$status_to_notify = '';
		$status_changed   = false;
		if ( isset( $params['status'] ) ) {
			$status = sanitize_text_field( (string) $params['status'] );
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				return new \WP_REST_Response( [ 'success' => false, 'message' => 'Status tidak valid.' ], 400 );
			}
			update_post_meta( $id, '_status', $status );
			$status_to_notify = $status;
			$status_changed   = ( $old_status !== $status );
		}

		// Field opsional lain (semua divalidasi ulang lewat sanitize_input + whitelist).
		$editable = [
			'nama', 'nip', 'jenis', 'prodi', 'skema', 'judul', 'ringkasan',
			'jml_tim', 'anggota', 'email', 'hp', 'jenis_hibah', 'sdgs', 'kelompok_keahlian',
		];
		foreach ( $editable as $f ) {
			if ( ! isset( $params[ $f ] ) ) {
				continue;
			}
			$clean = $this->sanitize_input( [ $f => $params[ $f ] ] );
			$val   = $clean[ $f ];
			$map   = [
				'nama' => '_nama', 'nip' => '_nip', 'jenis' => '_jenis',
				'prodi' => '_prodi', 'skema' => '_skema', 'judul' => '_judul',
				'ringkasan' => '_ringkasan', 'jml_tim' => '_jml_tim',
				'anggota' => '_anggota', 'email' => '_email', 'hp' => '_hp',
				'jenis_hibah' => '_jenis_hibah', 'sdgs' => '_sdgs',
				'kelompok_keahlian' => '_kelompok_keahlian',
			];
			update_post_meta( $id, $map[ $f ], $val );
		}

		// Anggota tim dinamis — array JSON / array (maks 2 dosen + 2 mahasiswa).
		if ( isset( $params['anggota_list'] ) ) {
			$clean = $this->sanitize_input( [ 'anggota_list' => $params['anggota_list'] ] );
			// Simpan sebagai array serialisasi WP agar konsisten dengan TR repeater.
			// Reader menangani string JSON maupun array, jadi aman untuk data lama.
			update_post_meta( $id, '_anggota_list', $clean['anggota_list'] );
			// Sinkron jumlah tim otomatis bila jml_tim tidak dikirim eksplisit.
			if ( ! isset( $params['jml_tim'] ) ) {
				update_post_meta( $id, '_jml_tim', (string) ( count( $clean['anggota_list'] ) + 1 ) );
			}
			// Perbarui post_content agar ringkasan anggota di CPT tetap akurat.
			$post_content = $this->format_anggota_html( $clean['anggota_list'] );
			$current = get_post_field( 'post_content', $id, 'raw' );
			if ( false !== strpos( (string) $current, '<strong>Anggota Tim' ) ) {
				$new_content = preg_replace( '/<ul[^>]*>.*?<\/ul>/s', $post_content, (string) $current, 1 );
				if ( $new_content ) {
					wp_update_post( [ 'ID' => $id, 'post_content' => $new_content ] );
				}
			}
		}

		// ── File proposal (opsional, multipart/form-data) — ganti PDF bila ada upload baru ──
		$file_params   = $request->get_file_params();
		$proposal_file = $file_params['proposal'] ?? null;
		if ( is_array( $proposal_file ) && isset( $proposal_file['error'] ) && (int) $proposal_file['error'] !== UPLOAD_ERR_NO_FILE ) {
			if ( (int) $proposal_file['error'] !== UPLOAD_ERR_OK ) {
				return new \WP_REST_Response( [ 'success' => false, 'message' => 'Upload proposal gagal.', 'errors' => [ 'proposal' => 'Upload proposal gagal (error ' . (int) $proposal_file['error'] . ').' ] ], 400 );
			}
			$reg_no = (string) get_post_meta( $id, '_reg_no', true );
			if ( '' === trim( $reg_no ) ) { $reg_no = (string) $id; }
			$new_att = $this->upload_proposal( $proposal_file, $reg_no );
			if ( is_wp_error( $new_att ) ) {
				return new \WP_REST_Response( [ 'success' => false, 'message' => $new_att->get_error_message(), 'errors' => [ 'proposal' => $new_att->get_error_message() ] ], 400 );
			}
			// Hapus attachment lama (jika ada) agar tidak menumpuk di Media Library.
			$old_att = (int) get_post_meta( $id, '_proposal_id', true );
			if ( $old_att && $old_att !== (int) $new_att ) {
				wp_delete_attachment( $old_att, true );
			}
			update_post_meta( $id, '_proposal_id', $new_att );
			update_post_meta( $id, '_proposal_url', wp_get_attachment_url( $new_att ) );
		}

		$updated_url  = (string) get_post_meta( $id, '_proposal_url', true );
		$updated_pid  = get_post_meta( $id, '_proposal_id', true );
		$updated_list = get_post_meta( $id, '_anggota_list', true );
		if ( is_string( $updated_list ) ) { $tmp = json_decode( $updated_list, true ); if ( is_array( $tmp ) ) { $updated_list = $tmp; } }
		if ( ! is_array( $updated_list ) ) { $updated_list = []; }

		// Setiap update status → kirim email ke pemohon (otomatis, sesuai permintaan table).
		$email_sent  = null;
		$email_error = '';
		if ( '' !== $status_to_notify ) {
			$label_map = [ 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'revised' => 'Revised', 'approved' => 'Approved', 'rejected' => 'Rejected', 'done' => 'Done' ];
			$label     = $label_map[ $status_to_notify ] ?? ucfirst( $status_to_notify );
			$note      = $status_changed ? ( 'Status diperbarui: ' . $label ) : ( 'Status: ' . $label );
			$res       = $this->send_applicant_email( $id, $note );
			if ( is_wp_error( $res ) ) {
				$email_sent  = false;
				$email_error = $res->get_error_message();
			} else {
				$email_sent = true;
			}
		}

		return new \WP_REST_Response( [
			'success'      => true,
			'message'      => 'Data diperbarui.',
			'proposal_url' => $updated_url,
			'proposal_id'  => $updated_pid,
			'anggota_list' => $updated_list,
			'status'       => $status_to_notify ?: (string) get_post_meta( $id, '_status', true ),
			'email_sent'   => $email_sent,
			'email_error'  => $email_error,
		], 200 );
	}

	/**
	 * Hapus pendaftaran (admin).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle_delete( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'pendaftaran_hibah' !== $post->post_type ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Data tidak ditemukan.' ], 404 );
		}

		$deleted = wp_delete_post( $id, true );
		if ( ! $deleted ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Gagal menghapus data.' ], 500 );
		}

		return new \WP_REST_Response( [ 'success' => true, 'message' => 'Data dihapus.' ], 200 );
	}

	/* ────────────────────────────────────────────────────────────
	 *  HELPERS
	 * ──────────────────────────────────────────────────────────── */

	private function get_latest_active_hibah_id(): int {
		$q = new \WP_Query( [
			'post_type'      => 'hibah',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date', 'order' => 'DESC',
		] );
		return $q->have_posts() ? $q->posts[0]->ID : 0;
	}

	private function generate_reg_no(): string {
		$year = date( 'Y' );
		$q    = new \WP_Query( [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'orderby'        => 'date', 'order' => 'DESC',
			'meta_query'     => [ [ 'key' => '_reg_no', 'value' => $year . '-', 'compare' => 'LIKE' ] ],
		] );
		$seq = $q->have_posts() ? ( (int) substr( get_post_meta( $q->posts[0]->ID, '_reg_no', true ), -5 ) + 1 ) : 1;
		return sprintf( '%s-%05d', $year, $seq );
	}

	private function save_submission( array $params, string $reg_no, int $hibah_id ): int|\WP_Error {
		$event_title = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';

		$title = sprintf( '[%s] %s — %s', $reg_no, $params['nama'], $params['judul'] );

		$content = sprintf(
			"<p><strong>Event Hibah:</strong> %s</p>\n" .
			"<p><strong>Nama:</strong> %s</p>\n" .
			"<p><strong>NIP/NIDN:</strong> %s</p>\n" .
			"<p><strong>Jenis:</strong> %s</p>\n" .
			"<p><strong>Prodi:</strong> %s</p>\n" .
			"<p><strong>Model Hibah:</strong> %s</p>\n" .
			"<p><strong>Jenis Hibah:</strong> %s</p>\n" .
			"<p><strong>SDGs:</strong> %s</p>\n" .
			"<p><strong>Kelompok Keahlian:</strong> %s</p>\n" .
			"<p><strong>Judul Usulan:</strong> %s</p>\n" .
			"<p><strong>Ringkasan:</strong> %s</p>\n" .
			"<p><strong>Anggota Tim:</strong></p>\n%s\n" .
			"<p><strong>Email:</strong> %s | <strong>WhatsApp:</strong> %s</p>",
			esc_html( $event_title ?: '—' ),
			esc_html( $params['nama'] ), esc_html( $params['nip'] ),
			esc_html( $params['jenis'] ), esc_html( $params['prodi'] ),
			esc_html( $params['skema'] ),
			esc_html( $params['jenis_hibah'] ?: '—' ),
			esc_html( $params['sdgs'] ?: '—' ),
			esc_html( $params['kelompok_keahlian'] ?: '—' ),
			esc_html( $params['judul'] ),
			esc_html( $params['ringkasan'] ),
			$this->format_anggota_html( $params['anggota_list'] ),
			esc_html( $params['email'] ), esc_html( $params['hp'] )
		);

		$post_id = wp_insert_post( [
			'post_type'    => 'pendaftaran_hibah',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $content,
			'post_parent'  => $hibah_id > 0 ? $hibah_id : 0,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Jumlah tim: kalau kosong, hitung otomatis dari anggota tim terisi + 1 (ketua).
		$jml_tim = (string) $params['jml_tim'];
		if ( '' === trim( $jml_tim ) || '0' === trim( $jml_tim ) ) {
			$jml_tim = (string) ( count( $params['anggota_list'] ) + 1 );
		}

		$meta = [
			'_reg_no'    => $reg_no, '_hibah_id' => $hibah_id,
			'_nama'      => $params['nama'], '_nip' => $params['nip'],
			'_jenis'     => $params['jenis'], '_prodi' => $params['prodi'],
			'_prodi_id'  => $params['prodi_id'],
			'_skema'     => $params['skema'], '_skema_id' => $params['skema_id'],
			'_judul'     => $params['judul'],
			'_ringkasan' => $params['ringkasan'], '_jml_tim' => $jml_tim,
			'_anggota'   => $params['anggota'], '_email' => $params['email'],
			'_hp'        => $params['hp'],
			'_jenis_hibah'      => $params['jenis_hibah'], '_jenis_hibah_id' => $params['jenis_hibah_id'],
			'_sdgs'             => $params['sdgs'], '_sdgs_id' => $params['sdgs_id'],
			'_kelompok_keahlian' => $params['kelompok_keahlian'], '_kk_id' => $params['kk_id'],
			'_status'    => 'submitted',
		];
		$meta['_anggota_list'] = wp_json_encode( $params['anggota_list'] );

		foreach ( $meta as $mk => $mv ) {
			update_post_meta( $post_id, $mk, $mv );
		}

		return $post_id;
	}

	private function send_admin_email( array $params, string $reg_no, int $hibah_id ): void {
		$admin_email = get_option( 'lp2m_site_admin_email', '' ) ?: get_option( 'admin_email' );
		$admin_email = is_email( $admin_email ) ? $admin_email : get_option( 'admin_email' );
		if ( empty( $admin_email ) ) { return; }

		$event_name = $hibah_id > 0 ? get_the_title( $hibah_id ) : '';
		$site_url   = get_site_url();
		$admin_link = $site_url . '/wp-admin/post.php?post=' . $this->last_post_id . '&action=edit';

		// Lampiran PDF detail pendaftaran (nama file: REGNO-detail.pdf).
		// PDF berisi data pribadi pendaftar → dipakai temp file yang langsung
		// dihapus setelah kirim, TIDAK disimpan permanen di folder uploads.
		$attachment = ITSI_LP2M_PDF::create_attachment( $params, $reg_no, $event_name );
		$attachments = $attachment ? [ $attachment ] : [];

		$to        = [ $admin_email ];
		$subject   = sprintf( '[LP2M] Pendaftaran Hibah Baru — %s', $reg_no );
		$body      = $this->email_html( $params, $reg_no, $event_name, $admin_link );
		$headers   = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail( $to, $subject, $body, $headers, $attachments );

		// Email konfirmasi ke pendaftar (sama konten, tanpa link admin + lampiran PDF).
		if ( is_email( $params['email'] ) ) {
			wp_mail(
				$params['email'],
				sprintf( 'Konfirmasi Pendaftaran Hibah — %s', $reg_no ),
				$this->email_html( $params, $reg_no, $event_name, '' ),
				[ 'Content-Type: text/html; charset=UTF-8' ],
				$attachments
			);
		}

		// Bersihkan file temp lampiran.
		ITSI_LP2M_PDF::cleanup( $attachment );
	}

	/**
	 * Template email HTML untuk admin + pendaftar.
	 */
	private function email_html( array $params, string $reg_no, string $event_name, string $admin_link ): string {
		$row = function ( string $label, string $value ): string {
			return '<tr><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;vertical-align:top">'
				. esc_html( $label )
				. '</td><td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;color:#111827">'
				. esc_html( $value ?: '—' ) . '</td></tr>';
		};

		$anggota_rows = '';
		foreach ( $params['anggota_list'] as $i => $m ) {
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$anggota_rows .= $row( 'Anggota #' . ( $i + 1 ), sprintf( '%s — %s (NIM: %s, Prodi: %s)', $m['nama'] ?? '', $tipe, $m['nomor'] ?? '', $m['prodi'] ?? '—' ) );
			} else {
				$anggota_rows .= $row( 'Anggota #' . ( $i + 1 ), sprintf( '%s — %s (NIDN: %s)', $m['nama'] ?? '', $tipe, $m['nomor'] ?? '' ) );
			}
		}

		$admin_btn = '';
		if ( '' !== $admin_link ) {
			$admin_btn = '<p style="margin:20px 0 0"><a href="' . esc_url( $admin_link ) . '" style="display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">Lihat Detail Pendaftaran</a></p>';
		}

		// Link "Cek Status" untuk pendaftar — ambil dari setting URL frontend.
		$frontend_url = untrailingslashit( (string) get_option( 'lp2m_site_frontend_url', '' ) );
		if ( '' === $frontend_url ) {
			// Fallback: URL publik LP2M (apabila setting belum diisi di produksi).
			$frontend_url = 'https://lp2m.bagistudio.com';
		}
		$track_btn    = '';
		if ( '' !== $frontend_url ) {
			$track_url = $frontend_url . '/daftar/status/' . rawurlencode( $reg_no );
			$track_btn = '<p style="margin:20px 0 0"><a href="' . esc_url( $track_url ) . '" style="display:inline-block;padding:10px 18px;background:#1f4d36;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">Cek Status Pendaftaran</a></p>';
		}

		return '<div style="background:#f3f4f6;padding:24px;font-family:Segoe UI,Arial,sans-serif">'
			. '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">'
			. '<div style="background:#0f172a;padding:20px 24px"><h2 style="margin:0;color:#fff;font-size:18px">' . esc_html( $event_name ?: 'LP2M ITSI' ) . '</h2>'
			. '<p style="margin:4px 0 0;color:#94a3b8;font-size:13px">No. Registrasi: <strong style="color:#f8fafc">' . esc_html( $reg_no ) . '</strong></p></div>'
			. '<div style="padding:24px">'
			. '<table style="width:100%;border-collapse:collapse;font-size:14px">'
			. $row( 'Nama', $params['nama'] )
			. $row( 'NIP/NIDN', $params['nip'] )
			. $row( 'Jenis Pengusul', $params['jenis'] )
			. $row( 'Program Studi', $params['prodi'] )
			. $row( 'Model Hibah', $params['skema'] )
			. $row( 'Jenis Hibah', $params['jenis_hibah'] )
			. $row( 'SDGs', $params['sdgs'] )
			. $row( 'Kelompok Keahlian', $params['kelompok_keahlian'] )
			. $row( 'Judul Usulan', $params['judul'] )
			. $row( 'Ringkasan', $params['ringkasan'] )
			. $anggota_rows
			. $row( 'Email', $params['email'] )
			. $row( 'WhatsApp', $params['hp'] )
			. '</table>'
			. $track_btn
			. $admin_btn
			. '<p style="margin:20px 0 0;color:#6b7280;font-size:12px">Email ini dikirim otomatis oleh sistem LP2M ITSI.</p>'
			. '</div></div></div>';
	}

	/**
	 * Format daftar anggota tim dinamis → HTML (untuk konten CPT + email HTML).
	 */
	private function format_anggota_html( array $list ): string {
		if ( empty( $list ) ) {
			return '<p>—</p>';
		}
		$html = '<ul style="margin:4px 0 0 20px">';
		foreach ( $list as $i => $m ) {
			$no   = (int) $i + 1;
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$html .= sprintf(
					'<li>%d. %s — %s (NIM: %s, Prodi: %s)</li>',
					$no, esc_html( $m['nama'] ?? '' ), esc_html( $tipe ),
					esc_html( $m['nomor'] ?? '' ), esc_html( $m['prodi'] ?? '—' )
				);
			} else {
				$html .= sprintf(
					'<li>%d. %s — %s (NIDN: %s)</li>',
					$no, esc_html( $m['nama'] ?? '' ), esc_html( $tipe ),
					esc_html( $m['nomor'] ?? '' )
				);
			}
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Format daftar anggota tim dinamis → plain text (untuk email admin).
	 */
	private function format_anggota_text( array $list ): string {
		if ( empty( $list ) ) {
			return '—';
		}
		$lines = [];
		foreach ( $list as $i => $m ) {
			$no   = (int) $i + 1;
			$tipe = ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) ? 'Mahasiswa' : 'Dosen';
			if ( 'mahasiswa' === ( $m['tipe'] ?? '' ) ) {
				$lines[] = sprintf( '%d. %s (%s, NIM %s, Prodi %s)', $no, $m['nama'] ?? '', $tipe, $m['nomor'] ?? '', $m['prodi'] ?? '—' );
			} else {
				$lines[] = sprintf( '%d. %s (%s, NIDN %s)', $no, $m['nama'] ?? '', $tipe, $m['nomor'] ?? '' );
			}
		}
		return implode( "\n", $lines );
	}

	/* ────────────────────────────────────────────────────────────
	 *  STATISTIK INFIGRAFIS (lp2m/v1/statistik)
	 * ──────────────────────────────────────────────────────────── */

	/**
	 * Agregasi pendaftaran hibah untuk infografis.
	 *
	 * Respons:
	 * {
	 *   "total_usulan": int,
	 *   "dosen_unik": int,
	 *   "mahasiswa_unik": int,
	 *   "jumlah_skema": int,
	 *   "skema_distribusi": [{ "label": string, "count": int }],
	 *   "jenis_distribusi": [{ "label": string, "count": int, "children": [{ "label": string, "count": int }] }],
	 *   "sdgs_trend": [{ "label": string, "count": int }],
	 *   "tahun_tersedia": ["2026","2025","2024"],
	 *   "tahun": "2026" | null
	 * }
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_statistik( $request ) {
		$tahun = (string) ( $request->get_param( 'tahun' ) ?? '' );
		$tahun = preg_match( '/^\d{4}$/', $tahun ) ? $tahun : '';

		// Cache transient 5 menit per tahun (data pendaftaran jarang berubah).
		$cache_key = 'lp2m_statistik_' . ( $tahun ?: 'all' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			$response = rest_ensure_response( $cached );
			$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=60' );
			$response->header( 'X-LP2M-Cache', 'HIT' );
			return $response;
		}

		$args = [
			'post_type'      => 'pendaftaran_hibah',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];
		if ( '' !== $tahun ) {
			$args['date_query'] = [ [ 'year' => (int) $tahun ] ];
		}
		$query = new WP_Query( $args );
		$ids   = $query->posts;

		$total_usulan    = count( $ids );
		$dosen_set       = [];
		$mahasiswa_set   = [];
		$skema_counts    = [];
		$jenis_counts    = [];   // parent → children counts
		$sdgs_counts     = [];
		$tahun_set       = [];

		foreach ( $ids as $post_id ) {
			// Tahun pendaftaran (dari post_date) untuk daftar tahun tersedia.
			$post_year = (int) get_the_date( 'Y', $post_id );
			if ( $post_year > 0 ) {
				$tahun_set[ $post_year ] = true;
			}

			// Pengusul (ketua).
			$nip = trim( (string) get_post_meta( $post_id, '_nip', true ) );
			if ( '' !== $nip ) {
				$dosen_set[ strtolower( $nip ) ] = true;
			}

			// Anggota tim.
			$list = json_decode( (string) get_post_meta( $post_id, '_anggota_list', true ), true );
			if ( is_array( $list ) ) {
				foreach ( $list as $m ) {
					$tipe = strtolower( (string) ( $m['tipe'] ?? '' ) );
					$nom  = trim( (string) ( $m['nomor'] ?? '' ) );
					if ( '' === $nom ) { continue; }
					$key = strtolower( $nom );
					if ( 'mahasiswa' === $tipe ) {
						$mahasiswa_set[ $key ] = true;
					} elseif ( 'dosen' === $tipe ) {
						$dosen_set[ $key ] = true;
					}
				}
			}

			// Skema.
			$skema = trim( (string) get_post_meta( $post_id, '_skema', true ) );
			if ( '' !== $skema ) {
				$skema_counts[ $skema ] = ( $skema_counts[ $skema ] ?? 0 ) + 1;
			}

			// Jenis hibah (hierarkis: "PENELITIAN" atau "PENELITIAN — Skema X").
			$jenis = trim( (string) get_post_meta( $post_id, '_jenis_hibah', true ) );
			if ( '' !== $jenis ) {
				$parts = array_map( 'trim', preg_split( '/\s*[—-]\s*/u', $jenis, 2 ) );
				// Normalisasi parent uppercase: gabungkan "Penelitian" & "PENELITIAN".
				$parent = strtoupper( (string) $parts[0] );
				if ( '' === $parent ) { $parent = strtoupper( $jenis ); }
				$jenis_counts[ $parent ] = $jenis_counts[ $parent ] ?? [ 'count' => 0, 'children' => [] ];
				$jenis_counts[ $parent ]['count']++;

				$child = $parts[1] ?? '';
				if ( '' !== $child ) {
					$jenis_counts[ $parent ]['children'][ $child ] = ( $jenis_counts[ $parent ]['children'][ $child ] ?? 0 ) + 1;
				}
			}

			// SDGs.
			$sdgs = trim( (string) get_post_meta( $post_id, '_sdgs', true ) );
			if ( '' !== $sdgs ) {
				// Normalisasi: buang prefix nomor SDG ("9 Industry..." → "Industry...")
				// agar label dengan & tanpa nomor tergabung jadi satu.
				$sdgs = preg_replace( '/^\d{1,2}\s*(?:[-–—]\s*)?/', '', $sdgs );
				$sdgs = trim( (string) $sdgs );
				if ( '' !== $sdgs ) {
					$sdgs_counts[ $sdgs ] = ( $sdgs_counts[ $sdgs ] ?? 0 ) + 1;
				}
			}
		}

		// Urutkan skema & SDGs berdasarkan jumlah (desc), lalu label (asc).
		$sort_by_count = function ( array $a, array $b ): int {
			if ( $a['count'] === $b['count'] ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
			return $b['count'] - $a['count'];
		};

		$skema_distribusi = [];
		foreach ( $skema_counts as $label => $count ) {
			$skema_distribusi[] = [ 'label' => $label, 'count' => (int) $count ];
		}
		usort( $skema_distribusi, $sort_by_count );

		// Jenis hibah hierarkis: parent (PENELITIAN/PENGABDIAN) + children (Skema X).
		$jenis_distribusi = [];
		foreach ( $jenis_counts as $parent => $info ) {
			$children = [];
			foreach ( $info['children'] as $child_label => $child_count ) {
				$children[] = [ 'label' => $child_label, 'count' => (int) $child_count ];
			}
			usort( $children, $sort_by_count );
			$jenis_distribusi[] = [
				'label'    => $parent,
				'count'    => (int) $info['count'],
				'children' => $children,
			];
		}
		usort( $jenis_distribusi, $sort_by_count );

		$sdgs_trend = [];
		foreach ( $sdgs_counts as $label => $count ) {
			$sdgs_trend[] = [ 'label' => $label, 'count' => (int) $count ];
		}
		usort( $sdgs_trend, $sort_by_count );

		$tahun_tersedia = array_keys( $tahun_set );
		rsort( $tahun_tersedia, SORT_NUMERIC );
		$tahun_tersedia = array_map( 'strval', $tahun_tersedia );

		$data = [
			'total_usulan'      => $total_usulan,
			'dosen_unik'        => count( $dosen_set ),
			'mahasiswa_unik'    => count( $mahasiswa_set ),
			'jumlah_skema'      => count( $skema_counts ),
			'skema_distribusi'  => $skema_distribusi,
			'jenis_distribusi'  => $jenis_distribusi,
			'sdgs_trend'        => $sdgs_trend,
			'tahun_tersedia'    => $tahun_tersedia,
			'tahun'             => '' !== $tahun ? $tahun : null,
		];

		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=60' );
		$response->header( 'X-LP2M-Cache', 'MISS' );
		return $response;
	}

	/* ────────────────────────────────────────────────────────────
	 *  TypeRocket — sync file proposal pendaftaran
	 *  TR menyimpan _proposal_id (attachment ID); kanonik URL ada di
	 *  _proposal_url. Sinkronkan setiap save agar API/dashboard tetap
	 *  membaca URL yang benar + validasi PDF.
	 * ──────────────────────────────────────────────────────────── */

	/**
	 * Sinkron TR → meta kanonik (_proposal_url) & validasi PDF.
	 * Dipanggil via save_post setelah TypeRocket menyimpan meta.
	 */
	public function sync_proposal_from_tr( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( 'pendaftaran_hibah' !== get_post_type( $post_id ) ) { return; }

		$raw = get_post_meta( $post_id, '_proposal_id', true );
		if ( is_array( $raw ) ) { $raw = reset( $raw ); }
		$new_id = (int) $raw;

		if ( 0 === $new_id ) {
			// TR Clear → kosongkan URL kanonik.
			if ( '' !== (string) get_post_meta( $post_id, '_proposal_url', true ) ) {
				delete_post_meta( $post_id, '_proposal_url' );
			}
			return;
		}

		$mime = get_post_mime_type( $new_id );
		if ( 'application/pdf' !== $mime ) {
			delete_post_meta( $post_id, '_proposal_id' );
			delete_post_meta( $post_id, '_proposal_url' );
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>Proposal harus PDF — file non-PDF ditolak.</p></div>';
			} );
			return;
		}

		$url = wp_get_attachment_url( $new_id );
		if ( $url ) {
			update_post_meta( $post_id, '_proposal_url', $url );
		}
	}
}

( new ITSI_LP2M_Hibah_Receiver() )->init();
