<?php

defined( 'ABSPATH' ) or die();

class WPorg_Handbook_Init_Test extends WP_UnitTestCase {

	//
	//
	// TESTS
	//
	//


	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WPorg_Handbook_Init' ) );
	}

	public function test_hooks_after_setup_theme_to_initialize() {
		$this->assertEquals( 10, has_action( 'after_setup_theme', [ 'WPorg_Handbook_Init', 'init' ] ) );
	}

	public function test_registers_default_hooks() {
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', [ 'WPorg_Handbook_Init' , 'enqueue_styles' ] ) );
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', [ 'WPorg_Handbook_Init' , 'enqueue_scripts' ] ) );
	}

	/*
	 * get_post_types()
	 */

	public function test_get_post_types_default() {
		$this->assertEquals( ['handbook'], WPorg_Handbook_Init::get_post_types() );
	}

	public function test_get_post_types_filtered() {
		add_filter( 'handbook_post_types', function ( $pt ) { return ['plugins', 'themes']; } );

		$this->assertEquals( ['plugins', 'themes'], WPorg_Handbook_Init::get_post_types() );
	}

	/*
	 * enqueue_styles()
	 */

	public function test_enqueue_styles() {
		$this->assertFalse( wp_style_is( 'wporg-handbook-css', 'enqueued' ) );

		WPorg_Handbook_Init::enqueue_styles();

		$this->assertTrue( wp_style_is( 'wporg-handbook-css', 'enqueued' ) );
	}

	/*
	 * enqueue_scripts()
	 */

	public function test_enqueue_scripts() {
		$this->assertFalse( wp_script_is( 'wporg-handbook', 'enqueued' ) );

		WPorg_Handbook_Init::enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wporg-handbook', 'enqueued' ) );
	}

}