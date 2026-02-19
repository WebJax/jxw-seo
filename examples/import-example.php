<?php
/**
 * Example PHP script for programmatically adding data to LocalSEO Booster
 * 
 * Usage:
 * 1. Place this file in your WordPress root directory
 * 2. Run: php import-example.php
 * 
 * Or include it in your theme's functions.php for one-time execution
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

// Check if LocalSEO plugin is active
if ( ! class_exists( 'LocalSEO\Database' ) ) {
    die( "LocalSEO Booster plugin is not active!\n" );
}

// Initialize database class
$db = new LocalSEO\Database();

// Example 1: Add a single row
function add_single_row( $db ) {
    $data = [
        'city' => 'Dianalund',
        'zip' => '4293',
        'service_keyword' => 'Kloakmester',
        'custom_slug' => 'kloakmester-dianalund',
    ];

    $id = $db->insert( $data );
    
    if ( $id ) {
        echo "✓ Added row ID: $id - {$data['service_keyword']} in {$data['city']}\n";
        return $id;
    } else {
        echo "✗ Failed to add row\n";
        return false;
    }
}

// Example 2: Add multiple rows from an array
function add_multiple_rows( $db ) {
    $cities_services = [
        [ 'city' => 'Copenhagen', 'zip' => '1000', 'service' => 'Plumber' ],
        [ 'city' => 'Aarhus', 'zip' => '8000', 'service' => 'VVS' ],
        [ 'city' => 'Odense', 'zip' => '5000', 'service' => 'Elektriker' ],
        [ 'city' => 'Roskilde', 'zip' => '4000', 'service' => 'Kloakmester' ],
        [ 'city' => 'Aalborg', 'zip' => '9000', 'service' => 'Tømrer' ],
    ];

    $count = 0;
    foreach ( $cities_services as $item ) {
        $data = [
            'city' => $item['city'],
            'zip' => $item['zip'],
            'service_keyword' => $item['service'],
            // custom_slug will be auto-generated
        ];

        $id = $db->insert( $data );
        if ( $id ) {
            $count++;
            echo "✓ Added: {$item['service']} in {$item['city']}\n";
        }
    }

    echo "\nTotal rows added: $count\n";
}

// Example 3: Import from CSV file
function import_from_csv( $db, $csv_file ) {
    if ( ! file_exists( $csv_file ) ) {
        echo "CSV file not found: $csv_file\n";
        return;
    }

    $handle = fopen( $csv_file, 'r' );
    $header = fgetcsv( $handle ); // Skip header row
    
    $count = 0;
    while ( ( $row = fgetcsv( $handle ) ) !== false ) {
        $data = [
            'city' => $row[0],
            'zip' => $row[1],
            'service_keyword' => $row[2],
        ];

        $id = $db->insert( $data );
        if ( $id ) {
            $count++;
        }
    }

    fclose( $handle );
    echo "Imported $count rows from CSV\n";
}

// Example 4: Generate AI content for all rows
function generate_ai_for_all( $db ) {
    $rows = $db->get_rows_missing_ai();
    
    echo "Found " . count( $rows ) . " rows missing AI content\n";
    
    $success = 0;
    $failed = 0;

    foreach ( $rows as $row ) {
        echo "Generating AI content for: {$row->service_keyword} in {$row->city}...";
        
        $row_data = [
            'city' => $row->city,
            'zip' => $row->zip,
            'service_keyword' => $row->service_keyword,
        ];

        $ai_content = LocalSEO\AI_Engine::generate_content( $row_data );

        if ( is_wp_error( $ai_content ) ) {
            echo " ✗ Error: " . $ai_content->get_error_message() . "\n";
            $failed++;
        } else {
            $db->update( $row->id, $ai_content );
            echo " ✓ Success\n";
            $success++;
        }

        // Add delay to avoid rate limiting
        usleep( 500000 ); // 0.5 second delay
    }

    echo "\nAI Generation Complete:\n";
    echo "Success: $success\n";
    echo "Failed: $failed\n";
}

// Example 5: Update existing rows
function update_row_example( $db ) {
    // Update row with ID 1
    $id = 1;
    $data = [
        'ai_generated_intro' => 'Custom intro text here',
        'meta_title' => 'Custom meta title',
        'meta_description' => 'Custom meta description',
    ];

    $success = $db->update( $id, $data );
    
    if ( $success ) {
        echo "✓ Updated row ID: $id\n";
    } else {
        echo "✗ Failed to update row ID: $id\n";
    }
}

// Example 6: Matrix of cities x services
function generate_city_service_matrix( $db ) {
    $cities = [
        [ 'name' => 'Copenhagen', 'zip' => '1000' ],
        [ 'name' => 'Aarhus', 'zip' => '8000' ],
        [ 'name' => 'Odense', 'zip' => '5000' ],
    ];

    $services = [ 'Plumber', 'Electrician', 'HVAC', 'Carpenter' ];

    $count = 0;
    foreach ( $cities as $city ) {
        foreach ( $services as $service ) {
            $data = [
                'city' => $city['name'],
                'zip' => $city['zip'],
                'service_keyword' => $service,
            ];

            $id = $db->insert( $data );
            if ( $id ) {
                $count++;
                echo "✓ Added: {$service} in {$city['name']}\n";
            }
        }
    }

    echo "\nCreated matrix: $count pages\n";
}

// Run examples
echo "=== LocalSEO Booster - Import Examples ===\n\n";

// Uncomment the example you want to run:

// add_single_row( $db );
// add_multiple_rows( $db );
// import_from_csv( $db, 'cities-services.csv' );
// generate_ai_for_all( $db );
// update_row_example( $db );
// generate_city_service_matrix( $db );

echo "\nDone! Check LocalSEO > Data Center in WordPress admin.\n";
