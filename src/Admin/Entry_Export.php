<?php
/**
 * Entry Export Class
 *
 * Builds the CSV export of a form's entries.
 *
 * @package Formtura
 * @since 1.0.6
 */

namespace Formtura\Admin;

use Formtura\Database\Entries_DB;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entry_Export class.
 */
class Entry_Export {

	/**
	 * Entries read per query while paging through a form.
	 *
	 * An export has to cover every entry, but a single unbounded SELECT on a
	 * busy form would hold the whole table - meta included - in memory at
	 * once. Paging keeps each query's result set bounded.
	 */
	const BATCH_SIZE = 200;

	/**
	 * Columns describing the entry itself, before its field answers.
	 *
	 * @var string[]
	 */
	private static $meta_columns = array( 'id', 'created_at', 'is_read', 'ip_address', 'user_agent' );

	/**
	 * Entries source.
	 *
	 * @var object|null
	 */
	private $entries;

	/**
	 * Constructor.
	 *
	 * @since 1.0.6
	 * @param object|null $entries Optional entries source. Anything exposing
	 *        get_by_form(); injected by tests so paging can be exercised
	 *        without a database.
	 */
	public function __construct( $entries = null ) {
		$this->entries = ( is_object( $entries ) && method_exists( $entries, 'get_by_form' ) ) ? $entries : null;
	}

	/**
	 * The entries source, created on demand.
	 *
	 * @since 1.0.6
	 * @return object
	 */
	private function entries() {
		if ( null === $this->entries ) {
			$this->entries = new Entries_DB();
		}

		return $this->entries;
	}

	/**
	 * Export every entry belonging to one form.
	 *
	 * @since 1.0.6
	 * @param int        $form_id Form ID.
	 * @param array|null $form    Form definition, used for column headings.
	 * @return string CSV text.
	 */
	public function for_form( $form_id, $form = null ) {
		return $this->csv( $this->read_all( $form_id ), $form );
	}

	/**
	 * Read every entry for a form, a page at a time.
	 *
	 * The old exporter called fta_get_entries() with no arguments, which
	 * applies the default 20-row limit - so any form past its first page
	 * exported a truncated file with nothing to say so.
	 *
	 * Paged with a keyset cursor (the id of the last row read) rather than
	 * OFFSET. OFFSET counts rows from the start of the result on every call,
	 * so an entry submitted while the export is still paging shifts every
	 * row after it and the next OFFSET lands on the wrong row - serving one
	 * row twice or, depending on timing, skipping one entirely. Seeking past
	 * the last id actually read is unaffected by what gets inserted
	 * elsewhere in the table.
	 *
	 * @since 1.0.6
	 * @param int $form_id Form ID.
	 * @return array[] Entries.
	 */
	private function read_all( $form_id ) {
		$all      = array();
		$after_id = null;

		do {
			$args = array( 'per_page' => self::BATCH_SIZE );

			if ( null !== $after_id ) {
				$args['after_id'] = $after_id;
			}

			$batch = $this->entries()->get_by_form( $form_id, $args );

			if ( ! is_array( $batch ) || empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $entry ) {
				$all[]    = $entry;
				$after_id = $entry['id'];
			}

			$batch_count = count( $batch );
		} while ( self::BATCH_SIZE === $batch_count );

		return $all;
	}

	/**
	 * Render entries as CSV.
	 *
	 * @since 1.0.6
	 * @param array      $entries Entries in Entries_DB::get_by_form() shape.
	 * @param array|null $form    Form definition, used for column headings.
	 * @return string CSV text.
	 */
	public function csv( array $entries, $form = null ) {
		if ( empty( $entries ) ) {
			return '';
		}

		$labels  = Entry_Values::labels( $form );
		$columns = $this->field_columns( $entries, $labels );

		$output = fopen( 'php://temp', 'r+' );

		fputcsv( $output, $this->header_row( $columns, $labels ) );

		foreach ( $entries as $entry ) {
			fputcsv( $output, $this->data_row( $entry, $columns ) );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Decide which field columns the file has, and in what order.
	 *
	 * Form order first so the file reads like the form, then any key no
	 * longer in the definition - a since-deleted field - in the order it was
	 * first seen. Taking the columns from the first entry alone, as the old
	 * exporter did, dropped every field that entry happened not to carry and
	 * shifted the remaining values under the wrong headings.
	 *
	 * @since 1.0.6
	 * @param array $entries Entries.
	 * @param array $labels  Label map.
	 * @return string[] Ordered data keys.
	 */
	private function field_columns( array $entries, array $labels ) {
		$seen = array();

		foreach ( $entries as $entry ) {
			if ( ! isset( $entry['data'] ) || ! is_array( $entry['data'] ) ) {
				continue;
			}

			foreach ( array_keys( $entry['data'] ) as $key ) {
				$seen[ (string) $key ] = true;
			}
		}

		$columns = array();

		foreach ( array_keys( $labels ) as $key ) {
			if ( isset( $seen[ $key ] ) ) {
				$columns[] = $key;
				unset( $seen[ $key ] );
			}
		}

		foreach ( array_keys( $seen ) as $key ) {
			// The computed payment order is not a field answer; it reads last,
			// after everything the visitor actually filled in.
			if ( Entry_Values::PAYMENT_KEY === $key ) {
				continue;
			}

			$columns[] = $key;
		}

		if ( isset( $seen[ Entry_Values::PAYMENT_KEY ] ) ) {
			$columns[] = Entry_Values::PAYMENT_KEY;
		}

		return $columns;
	}

	/**
	 * Build the header row.
	 *
	 * @since 1.0.6
	 * @param string[] $columns Ordered data keys.
	 * @param array    $labels  Label map.
	 * @return string[]
	 */
	private function header_row( array $columns, array $labels ) {
		$row = array(
			__( 'Entry ID', 'formtura' ),
			__( 'Submitted', 'formtura' ),
			__( 'Status', 'formtura' ),
			__( 'IP Address', 'formtura' ),
			__( 'User Agent', 'formtura' ),
		);

		foreach ( $columns as $key ) {
			$row[] = Entry_Values::label( $key, $labels );
		}

		// Labels come from a form definition an author typed, so a heading is
		// no more trustworthy than a cell.
		return array_map( array( $this, 'neutralize' ), $row );
	}

	/**
	 * Build one entry's row.
	 *
	 * @since 1.0.6
	 * @param array    $entry   Entry.
	 * @param string[] $columns Ordered data keys.
	 * @return string[]
	 */
	private function data_row( array $entry, array $columns ) {
		$data = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array();

		$row = array(
			isset( $entry['id'] ) ? (string) $entry['id'] : '',
			isset( $entry['created_at'] ) ? (string) $entry['created_at'] : '',
			! empty( $entry['is_read'] ) ? __( 'Read', 'formtura' ) : __( 'Unread', 'formtura' ),
			isset( $entry['ip_address'] ) ? (string) $entry['ip_address'] : '',
			isset( $entry['user_agent'] ) ? (string) $entry['user_agent'] : '',
		);

		foreach ( $columns as $key ) {
			$row[] = array_key_exists( $key, $data ) ? Entry_Values::text_for( $key, $data[ $key ] ) : '';
		}

		return array_map( array( $this, 'neutralize' ), $row );
	}

	/**
	 * Stop a cell from being evaluated as a spreadsheet formula.
	 *
	 * Excel, LibreOffice and Sheets all treat a cell opening with =, +, - or @
	 * as a formula, and leading whitespace (including a tab or carriage
	 * return) does not change that. Submitted text reaching a spreadsheet
	 * unaltered is therefore code an administrator runs by opening the export,
	 * so anything that could open a formula is prefixed with an apostrophe -
	 * the spreadsheet convention for "this cell is literal text".
	 *
	 * Values that are plainly numeric are left alone: a negative amount or a
	 * signed rating is not a formula, and quoting it would turn every numeric
	 * column into text.
	 *
	 * @since 1.0.6
	 * @param string $value Cell value.
	 * @return string
	 */
	private function neutralize( $value ) {
		$value = (string) $value;

		if ( '' === $value || is_numeric( $value ) ) {
			return $value;
		}

		if ( 1 === preg_match( '/^[\s]*[=+\-@]/', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
