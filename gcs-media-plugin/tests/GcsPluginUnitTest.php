<?php
/**
 * Copyright 2016 Google Inc.
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.  This program is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 * Public License for more details.  You should have received a copy of the
 * GNU General Public License along with this program; if not, write to the
 * Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 *
 * @package Google\Cloud\Storage\WordPress\Test;
 */

namespace Google\Cloud\Storage\WordPress\Test;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\WordPress;
use Google\Cloud\Storage\WordPress\Uploads\Uploads;

/**
 * Unit tests for the plugin.
 */
class GcsPluginUnitTest extends TestCase
{

    /**
     * A test for options_page_view.
     */
    public function test_options_page_view(): void
    {
        // Nothing for normal user
        ob_start();
        WordPress\options_page_view();
        $html = ob_get_clean();
        $this->assertEmpty($html);

        // Showing options form to admins.
        $user_id = $this->factory->user->create(
            array('role' => 'administrator')
        );
        \wp_set_current_user($user_id);
        ob_start();
        WordPress\options_page_view();
        $html = ob_get_clean();
        $this->assertMatchesRegularExpression('/form action="options.php"/', $html);
    }

    /**
     * A test for options_page.
     */
    public function test_options_page(): void
    {
        $output = WordPress\options_page();
        $this->assertEmpty($output);

        // Showing options form to admins.
        $user_id = $this->factory->user->create(
            array('role' => 'administrator')
        );
        \wp_set_current_user($user_id);

        $output = WordPress\options_page();

        $this->assertEquals('admin_page_gcs', $output);
    }

    /**
     * A test for activation_hook.
     */
    public function test_activation_hook(): void
    {
        // Flag to check if our custom function was called
        $functionCalled = false;

        // Custom function to attach to the 'gcs_activation' action hook
        $customFunction = function () use (&$functionCalled) {
            $functionCalled = true;
        };

        // Attach the custom function to the 'gcs_activation' action hook
        add_action('gcs_activation', $customFunction);

        // Call the function we're testing
        WordPress\activation_hook();

        // Check if our custom function was called
        $this->assertTrue($functionCalled);
    }

    /**
     * A test for settings_link.
     */
    public function test_settings_link(): void
    {
        $links = [];
        $links = WordPress\settings_link(
            $links,
            \plugin_basename(WordPress\PLUGIN_PATH)
        );
        $this->assertMatchesRegularExpression('/options-general.php\\?page=gcs/', $links[0]);
    }

    /**
     * A test for register_settings().
     */
    public function test_register_settings(): void
    {
        // There is no settings initially.
        $ssl = get_option(Uploads::USE_HTTPS_OPTION);
        $this->assertFalse($ssl);
        // We have the option set to true (1).
        WordPress\register_settings();
        $ssl = get_option(Uploads::USE_HTTPS_OPTION);
        $this->assertEquals(1, $ssl);
    }

    /**
     * A test for filter_delete_file.
     */
    public function test_filter_delete_file()
    {
        $result = Uploads::filter_delete_file(
            'gs://tmatsuo-test-wordpress/testfile'
        );
        $this->assertEquals(
            'gs://tmatsuo-test-wordpress/testfile',
            $result
        );
    }

    /**
     * A test for filter_upload_dir.
     */
    public function test_filter_upload_dir()
    {
        WordPress\register_settings();
        // It does nothing without setting the option.
        $values = array();
        $values = Uploads::filter_upload_dir($values);
        $this->assertEmpty($values);

        $testBucket = getenv('TEST_BUCKET');
        if ($testBucket === false) {
            $this->markTestSkipped('TEST_BUCKET envvar is not set');
        }
        $values = array(
            'path' => '/tmp/uploads',
            'subdir' => '/2016/11',
            'url' => 'https://example.com/wp-content/2016/11/uploaded.jpg',
            'basedir' => '/tmp/uploads',
            'baseurl' => 'https://example.com/wp-content/2016/11/',
            'error' => false
        );
        \update_option(Uploads::BUCKET_OPTION, $testBucket);
        $values = Uploads::filter_upload_dir($values);
        $this->assertEquals(
            sprintf('gs://%s/1/2016/11', $testBucket),
            $values['path']
        );
        $this->assertEquals('/2016/11', $values['subdir']);
        $this->assertFalse($values['error']);
        $this->assertEquals(
            sprintf('https://storage.googleapis.com/%s/1/2016/11', $testBucket),
            $values['url']
        );
        $this->assertEquals(
            sprintf('gs://%s/1', $testBucket),
            $values['basedir']
        );
        $this->assertEquals(
            sprintf('https://storage.googleapis.com/%s/1', $testBucket),
            $values['baseurl']
        );
    }

    /**
     * A test for bucket_form.
     */
    public function test_bucket_form()
    {
        ob_start();
        Uploads::bucket_form();
        $html = ob_get_clean();
        $this->assertMatchesRegularExpression(
            '/<input id="gcs_bucket" name="gcs_bucket" type="text" value="">/',
            $html
        );
    }

    /**
     * A test for use_https_form.
     */
    public function test_use_https_form()
    {
        ob_start();
        Uploads::use_https_form();
        $html = ob_get_clean();
        $this->assertMatchesRegularExpression(
            '/input id="gcs_use_https_for_media", name="gcs_use_https_for_media" type="checkbox"/',
            $html
        );
    }

    /**
     * A test for validate_bucket.
     */
    public function test_validate_bucket()
    {
        $testBucket = getenv('TEST_BUCKET');
        if ($testBucket === false) {
            $this->markTestSkipped('TEST_BUCKET envvar is not set');
        }
        Uploads::validate_bucket($testBucket);
    }

    /**
     * A test for validate_use_https.
     */
    public function test_validate_use_https()
    {
        $result = Uploads::validate_use_https(0);
        $this->assertFalse($result);
        $result = Uploads::validate_use_https(1);
        $this->assertTrue($result);
    }

    /**
     * A test for getting the StorageClient
     */
    public function test_get_storage_client()
    {
        $testBucket = getenv('TEST_BUCKET');
        if ($testBucket === false) {
            $this->markTestSkipped('TEST_BUCKET envvar is not set');
        }
        $req = null;
        $storage = WordPress\get_google_storage_client(
            function ($request, $options) use (&$req) {
                // Store request in local variable for testing
                $req = $request;
                // Return a mock response
                $response = $this->createMock('Psr\Http\Message\ResponseInterface');
                $response->method('getBody')->will(
                    $this->returnValue('{"name": "", "generation": ""}')
                );
                return $response;
            }
        );

        // make an API call
        $bucket = $storage->bucket($testBucket);
        $bucket->upload(__FILE__, ['name' => 'foo']);

        $this->assertNotNull($req);
        $headerValues = $this->splitInfoHeader(
            $req->getHeaderLine('x-goog-api-client')
        );
        $this->assertEquals(4, count($headerValues));
        $this->assertArrayHasKey('gl-php', $headerValues);
        $this->assertEquals(PHP_VERSION, $headerValues['gl-php']);
        $this->assertArrayHasKey('gccl', $headerValues);
        $this->assertEquals(StorageClient::VERSION, $headerValues['gccl']);
        $this->assertArrayHasKey('wp', $headerValues);
        $this->assertArrayHasKey('wp-gcs', $headerValues);
    }

    /**
     * A test for test_get_wp_info_header.
     */
    public function test_get_wp_info_header()
    {
        $header = WordPress\get_wp_info_header();
        $headerValues = $this->splitInfoHeader($header);
        $this->assertEquals(2, count($headerValues));
        $this->assertArrayHasKey('wp', $headerValues);
        global $wp_version;
        $this->assertEquals($wp_version, $headerValues['wp']);
        $this->assertArrayHasKey('wp-gcs', $headerValues);
        $this->assertEquals(WordPress\PLUGIN_VERSION, $headerValues['wp-gcs']);
    }

    private function splitInfoHeader($header)
    {
        $headerValues = [];
        foreach (explode(' ', $header) as $part) {
            list($key, $val) = explode('/', $part);
            $headerValues[$key] = $val;
        }
        return $headerValues;
    }
}
