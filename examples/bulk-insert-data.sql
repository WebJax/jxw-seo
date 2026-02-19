-- Example SQL to bulk insert LocalSEO data
-- This can be used for importing large datasets into the plugin

-- Note: Replace 'wp_' with your WordPress database prefix if different

-- Example 1: Danish cities with plumbing services
INSERT INTO wp_localseo_data (city, zip, service_keyword, custom_slug, ai_generated_intro, meta_title, meta_description) VALUES
('Dianalund', '4293', 'Kloakmester', 'kloakmester-dianalund', '', 'Kloakmester i Dianalund - Professionel Kloakservice', 'Søger du en erfaren kloakmester i Dianalund? Vi tilbyder hurtig og pålidelig kloakservice. Kontakt os i dag!'),
('Roskilde', '4000', 'Kloakmester', 'kloakmester-roskilde', '', 'Kloakmester i Roskilde - Ekspert Kloakservice', 'Professionel kloakmester i Roskilde. Vi håndterer alle kloakproblemer med erfaring og kvalitet.'),
('Copenhagen', '1000', 'Plumber', 'plumber-copenhagen', '', 'Professional Plumber in Copenhagen', 'Expert plumbing services in Copenhagen. Fast, reliable, and affordable. Contact us today!'),
('Aarhus', '8000', 'VVS', 'vvs-aarhus', '', 'VVS i Aarhus - Din Lokale VVS-Mand', 'Søger du en dygtig VVS-mand i Aarhus? Vi tilbyder alt inden for VVS-arbejde.'),
('Odense', '5000', 'Elektriker', 'elektriker-odense', '', 'Elektriker i Odense - Autoriseret El-Installation', 'Professionel elektriker i Odense. Vi udfører alt el-arbejde sikkert og effektivt.');

-- Example 2: US cities with home services
-- INSERT INTO wp_localseo_data (city, zip, service_keyword, custom_slug) VALUES
-- ('New York', '10001', 'Plumber', 'plumber-new-york'),
-- ('Los Angeles', '90001', 'Electrician', 'electrician-los-angeles'),
-- ('Chicago', '60601', 'HVAC Repair', 'hvac-repair-chicago'),
-- ('Houston', '77001', 'Roofing', 'roofing-houston'),
-- ('Phoenix', '85001', 'Landscaping', 'landscaping-phoenix');

-- Example 3: Programmatic SEO for real estate
-- INSERT INTO wp_localseo_data (city, zip, service_keyword, custom_slug) VALUES
-- ('Miami', '33101', 'Homes for Sale', 'homes-for-sale-miami'),
-- ('Miami', '33101', 'Condos for Rent', 'condos-for-rent-miami'),
-- ('Miami', '33101', 'Apartments', 'apartments-miami'),
-- ('San Francisco', '94102', 'Homes for Sale', 'homes-for-sale-san-francisco'),
-- ('San Francisco', '94102', 'Luxury Condos', 'luxury-condos-san-francisco');

-- Example 4: Service combinations
-- INSERT INTO wp_localseo_data (city, zip, service_keyword, custom_slug) VALUES
-- ('Boston', '02101', 'Emergency Plumber', 'emergency-plumber-boston'),
-- ('Boston', '02101', '24/7 Plumber', '24-7-plumber-boston'),
-- ('Boston', '02101', 'Commercial Plumber', 'commercial-plumber-boston');

-- After inserting data, you can:
-- 1. Go to LocalSEO > Data Center in WordPress admin
-- 2. Click "Generate All Missing AI Fields" to auto-fill content
-- 3. Review and edit the generated content as needed

-- Query to check your data:
-- SELECT id, city, service_keyword, custom_slug, 
--        CASE WHEN ai_generated_intro != '' THEN 'Yes' ELSE 'No' END as has_ai_content
-- FROM wp_localseo_data
-- ORDER BY id DESC;

-- Query to find rows missing AI content:
-- SELECT id, city, service_keyword 
-- FROM wp_localseo_data 
-- WHERE ai_generated_intro = '' OR ai_generated_intro IS NULL;

-- Update a specific row:
-- UPDATE wp_localseo_data 
-- SET ai_generated_intro = 'Your custom intro text here',
--     meta_title = 'Custom meta title',
--     meta_description = 'Custom meta description'
-- WHERE id = 1;

-- Delete all data (use with caution!):
-- TRUNCATE TABLE wp_localseo_data;
