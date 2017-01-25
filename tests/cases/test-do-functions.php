<?php

class DoFunctionsTest extends WP_UnitTestCase {

	protected static $upload_url;
	protected static $upload_subdir;

	public static function setUpBeforeClass() {
		$wp_upload_dir       = wp_get_upload_dir();
		self::$upload_url    = $wp_upload_dir['url'];
		self::$upload_subdir = $wp_upload_dir['subdir'];
	}

	public static function tearDownAfterClass() {
		global $imgix_options;
		$imgix_options = [];
	}

	public function test_sanity_check() {
		$this->assertEquals( 'http://example.org/', home_url( '/' ) );
	}

	public function test_filter_wp_get_attachment_url_no_imgix_cdn() {
		$this->disable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.jpg' );
		$result          = apply_filters( 'wp_get_attachment_url', $upload_file_url );

		$this->assertEquals( $upload_file_url, $result );
	}

	public function test_filter_wp_get_attachment_url_with_imgix_cdn() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.jpg' );
		$expected        = $this->generate_cdn_file_url( 'example.jpg' );

		$result = apply_filters( 'wp_get_attachment_url', $upload_file_url );
		$this->assertEquals( $expected, $result );
	}

	public function test_filter_wp_get_attachment_url_size_arguments() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example-400x300.png' );
		$expected        = $this->generate_cdn_file_url( 'example-400x300.png' );

		$result = apply_filters( 'wp_get_attachment_url', $upload_file_url );
		$this->assertEquals( $expected, $result );
	}


	public function test_filter_wp_get_attachment_url_not_image() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.pdf' );
		$expected        = $upload_file_url;

		$result = apply_filters( 'wp_get_attachment_url', $upload_file_url );
		$this->assertEquals( $expected, $result );
	}

	public function test_filter_wp_calculate_image_srcset_no_cdn() {
		$this->disable_cdn();

		$size_array    = [ 400, 400 ];
		$image_src     = $this->generate_upload_file_url( 'example.png' );
		$image_meta    = [];
		$attachment_id = 0;

		$sources = [
			400 => [
				'url'        => $this->generate_upload_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_upload_file_url( 'example-300x300.png' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];

		$result = apply_filters( 'wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id );

		$this->assertEquals( $sources, $result );
	}

	public function test_filter_wp_calculate_image_srcset_with_cdn() {
		$this->enable_cdn();

		$size_array    = [ 400, 400 ];
		$image_src     = $this->generate_upload_file_url( 'example.png' );
		$image_meta    = [];
		$attachment_id = 0;

		$sources = [
			400 => [
				'url'        => $this->generate_upload_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_upload_file_url( 'example-300x300.png' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];

		$expected = [
			400 => [
				'url'        => $this->generate_cdn_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_cdn_file_url( 'example-300x300.png' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];


		$result = apply_filters( 'wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id );

		$this->assertEquals( $expected, $result );
	}

	public function test_imgix_replace_non_wp_images_no_cdn() {
		$this->disable_cdn();

		$string = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '" />';

		$this->assertEquals( $string, imgix_replace_non_wp_images( $string ) );
	}

	public function test_imgix_replace_non_wp_images_no_match() {
		$this->enable_cdn();

		$string = '<html><head></head><body></body></html>';

		$this->assertEquals( $string, imgix_replace_non_wp_images( $string ) );
	}

	public function test_imgix_replace_non_wp_images_other_src() {
		$this->enable_cdn();

		$string = '<img src="https://www.google.com/example.gif" />';

		$this->assertEquals( $string, imgix_replace_non_wp_images( $string ) );
	}

	public function test_imgix_replace_non_wp_images_with_cdn() {
		$this->enable_cdn();

		$string   = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '" />';
		$expected = '<img src="' . $this->generate_cdn_file_url( 'example.gif' ) . '" />';

		$this->assertEquals( $expected, imgix_replace_non_wp_images( $string ) );
	}

	public function test_imgix_replace_non_wp_images_size_arguments() {
		$this->enable_cdn();

		$string   = '<img src="' . $this->generate_upload_file_url( 'example-400x300.gif' ) . '" />';
		$expected = '<img src="' . $this->generate_cdn_file_url( 'example.gif?w=400&h=300' ) . '" />';

		$this->assertEquals( $expected, imgix_replace_non_wp_images( $string ) );
	}




	protected function generate_upload_file_url( $filename ) {
		return trailingslashit( self::$upload_url ) . $filename;
	}

	protected function generate_cdn_file_url( $filename ) {
		global $imgix_options;

		$file_url = parse_url( $this->generate_upload_file_url( $filename ) );
		$cdn      = parse_url( $imgix_options['cdn_link'] );

		foreach ( [ 'scheme', 'host', 'port' ] as $url_part ) {
			if ( isset( $cdn[ $url_part ] ) ) {
				$file_url[ $url_part ] = $cdn[ $url_part ];
			} else {
				unset( $file_url[ $url_part ] );
			}
		}

		$file_url = $this->unparse_url( $file_url );

		return $file_url;
	}

	protected function unparse_url( $parsed_url ) {
		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	protected function enable_cdn() {
		global $imgix_options;
		$imgix_options = [
			'cdn_link' => 'https://my-source.imgix.com'
		];
	}

	protected function disable_cdn() {
		global $imgix_options;
		$imgix_options = [];
	}
}

