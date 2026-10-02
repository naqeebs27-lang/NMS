Setup instructions
==================

1) Run the web application and open /setup.php in your browser.
2) Provide MySQL admin credentials (e.g., root) so the installer can create the application database and import the schema (sql/schema.sql).
3) Optionally provide an application DB user; check "Create application DB user" to have the installer create this user and grant privileges.
4) After successful setup, config.json will be created in the project root with DB and OAuth settings.

OAuth (Google / Microsoft)
--------------------------
1) Register an OAuth application in Google Cloud Console and in Microsoft Azure App Registrations.
2) For both providers set the redirect URI to: https://your-host/oauth_callback.php?provider=google (or provider=microsoft)
3) Copy client ID and client secret into the oauth section of config.json (or re-run setup to add them).
4) Users can sign in using the buttons on the login page; new users are auto-created by email.

Security notes
--------------
- Ensure config.json is not publicly writable by untrusted users.
- Prefer creating a dedicated application DB user rather than using root in production.
