-- D023/D033: the seed carries lab.mylab.com / patients.mylab.com / lab.arcdiagnosticslab.com.
-- Multisite matches the HTTP Host against wp_blogs, so rewrite everything to the
-- path-based test domain before WordPress ever loads.
--
-- Blog 1 = LIS admin      -> http://localhost/
-- Blog 2 = patient portal -> http://localhost/patients/

UPDATE `wp_blogs` SET `domain` = 'localhost', `path` = '/'          WHERE `blog_id` = 1;
UPDATE `wp_blogs` SET `domain` = 'localhost', `path` = '/patients/' WHERE `blog_id` = 2;

UPDATE `wp_site` SET `domain` = 'localhost', `path` = '/' WHERE `id` = 1;

UPDATE `wp_sitemeta` SET `meta_value` = 'http://localhost/'
  WHERE `site_id` = 1 AND `meta_key` = 'siteurl';
UPDATE `wp_sitemeta` SET `meta_value` = '0'
  WHERE `site_id` = 1 AND `meta_key` = 'subdomain_install';

UPDATE `wp_options`   SET `option_value` = 'http://localhost'
  WHERE `option_name` IN ('siteurl', 'home');
UPDATE `wp_2_options` SET `option_value` = 'http://localhost/patients'
  WHERE `option_name` IN ('siteurl', 'home');

-- Orphan network row 2 (patients.mypatients.com) has no blogs pointing at it.
-- Park it on an unroutable name so it can never match a request Host.
UPDATE `wp_site` SET `domain` = 'unused.invalid' WHERE `id` = 2;

-- WordPress interposes an "is this admin email still correct?" interstitial on
-- login when `admin_email_lifespan` has passed. It turns a 1-step login into a
-- 2-step one for every test run. Push it far out.
INSERT INTO `wp_options` (`option_name`,`option_value`,`autoload`)
  VALUES ('admin_email_lifespan','99999999999','yes')
  ON DUPLICATE KEY UPDATE `option_value`='99999999999';
INSERT INTO `wp_2_options` (`option_name`,`option_value`,`autoload`)
  VALUES ('admin_email_lifespan','99999999999','yes')
  ON DUPLICATE KEY UPDATE `option_value`='99999999999';
