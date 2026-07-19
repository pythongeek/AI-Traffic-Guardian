<?php
/**
 * Lightweight, zero-dependency server-side PDF generator class.
 *
 * @package AI_Traffic_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ATG_PDF_Writer
 */
class ATG_PDF_Writer {

	/**
	 * PDF output buffer.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Offsets of PDF objects for xref table.
	 *
	 * @var array
	 */
	private $offsets = array();

	/**
	 * Current PDF object index.
	 *
	 * @var int
	 */
	private $obj_count = 2;

	/**
	 * Page content streams.
	 *
	 * @var array
	 */
	private $pages = array();

	/**
	 * Add text layout content to page.
	 *
	 * @param string $content Raw content stream operators.
	 */
	public function add_page( $content ) {
		$this->pages[] = $content;
	}

	/**
	 * Output PDF file.
	 *
	 * @return string PDF content.
	 */
	public function output() {
		$this->buffer    = '';
		$this->offsets   = array();
		$this->obj_count = 2;

		$this->out( '%PDF-1.4' );

		// Obj 1: Catalog
		$this->offsets[1] = strlen( $this->buffer );
		$this->out( '1 0 obj' );
		$this->out( '<< /Type /Catalog /Pages 2 0 R >>' );
		$this->out( 'endobj' );

		// Obj 2: Pages list
		$kids = '';
		$total_pages = count( $this->pages );
		for ( $i = 0; $i < $total_pages; $i++ ) {
			$kids .= ( 3 + $i * 3 ) . ' 0 R ';
		}

		$this->offsets[2] = strlen( $this->buffer );
		$this->out( '2 0 obj' );
		$this->out( '<< /Type /Pages /Kids [' . trim( $kids ) . '] /Count ' . $total_pages . ' >>' );
		$this->out( 'endobj' );

		for ( $i = 0; $i < $total_pages; $i++ ) {
			$page_obj    = 3 + $i * 3;
			$content_obj = 4 + $i * 3;
			$font_obj    = 5 + $i * 3;

			// Page object
			$this->offsets[ $page_obj ] = strlen( $this->buffer );
			$this->out( $page_obj . ' 0 obj' );
			$this->out( '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 ' . $font_obj . ' 0 R >> >> /MediaBox [0 0 595 842] /Contents ' . $content_obj . ' 0 R >>' );
			$this->out( 'endobj' );

			// Content stream object (with standard page headers, title, and styling)
			$this->offsets[ $content_obj ] = strlen( $this->buffer );
			$this->out( $content_obj . ' 0 obj' );
			$this->out( '<< /Length ' . strlen( $this->pages[ $i ] ) . ' >>' );
			$this->out( 'stream' );
			$this->out( $this->pages[ $i ] );
			$this->out( 'endstream' );
			$this->out( 'endobj' );

			// Font object (Helvetica Standard)
			$this->offsets[ $font_obj ] = strlen( $this->buffer );
			$this->out( $font_obj . ' 0 obj' );
			$this->out( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>' );
			$this->out( 'endobj' );

			$this->obj_count = $font_obj;
		}

		$xref_pos = strlen( $this->buffer );
		$this->out( 'xref' );
		$this->out( '0 ' . ( $this->obj_count + 1 ) );
		$this->out( '0000000000 65535 f ' );

		for ( $i = 1; $i <= $this->obj_count; $i++ ) {
			$this->out( sprintf( '%010d 00000 n ', $this->offsets[ $i ] ) );
		}

		$this->out( 'trailer' );
		$this->out( '<< /Size ' . ( $this->obj_count + 1 ) . ' /Root 1 0 R >>' );
		$this->out( 'startxref' );
		$this->out( $xref_pos );
		$this->out( '%%EOF' );

		return $this->buffer;
	}

	/**
	 * Append content helper.
	 *
	 * @param string $s Output line.
	 */
	private function out( $s ) {
		$this->buffer .= $s . "\n";
	}
}
