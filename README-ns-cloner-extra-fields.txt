NS Cloner Extra Fields - Readme
================================

Location
--------
This customization is implemented as a Must-Use plugin:
- ns-cloner-extra-fields.php

Installation
------------
1. Make sure WordPress Multisite and NS Cloner are already installed and active.
2. Copy `ns-cloner-extra-fields.php` into:
   - `wp-content/mu-plugins/`
3. If the `mu-plugins` folder does not exist, create it:
   - `wp-content/mu-plugins/`
4. Do not place this file inside the NS Cloner plugin directory.
5. In WordPress Admin, go to Plugins > Must-Use and confirm this plugin is listed.
6. Open NS Cloner > Create New Site and verify the 3 extra fields appear.

Why MU plugin
-------------
This file is placed in wp-content/mu-plugins, outside the NS Cloner plugin folder.
Because of that, updating NS Cloner will not remove this customization.

What it adds
------------
In NS Cloner > Create New Site, this adds these fields:
1. Tagline (text)
2. Site Icon (image picker)
3. Administration Email Address (email)

How values are applied to cloned site
-------------------------------------
After cloning finishes (core mode), values are written to target site options:
- Tagline -> blogdescription
- Administration Email Address -> admin_email
- Site Icon -> site_icon (by sideloading selected image and saving attachment ID)

Validation
----------
- Administration Email Address is validated as an email if provided.
- If invalid, NS Cloner shows an inline validation error in the Create Target section.

Hooks used (update-safe integration)
------------------------------------
- ns_cloner_close_section_box_create_target
- ns_cloner_validate_site_errors
- ns_cloner_process_finish
- admin_enqueue_scripts (for WP media picker)

Maintenance notes
-----------------
- To change labels/placeholders/logic, edit: ns-cloner-extra-fields.php
- Do not move this file into the NS Cloner plugin directory.
- Keep this file in mu-plugins to survive NS Cloner updates.

Test checklist
--------------
1. Open NS Cloner page and confirm the 3 extra fields appear.
2. Clone a site with values set.
3. In target site Settings > General, confirm:
   - Tagline changed
   - Administration Email Address changed
4. In target site Customizer/Site Identity, confirm Site Icon is set.
