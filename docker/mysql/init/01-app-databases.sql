-- Runs once, on a fresh data volume.
--
-- Gives the app user rights over the test schemas so `make test` can migrate a
-- throwaway database without touching development data.
CREATE DATABASE IF NOT EXISTS metacreator_test
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- The pattern, not just `metacreator_test`, is what makes `--parallel` work:
-- Laravel gives each test process its own `metacreator_test_test_<n>` database and
-- expects to CREATE and DROP them itself. `\_` escapes the underscore so this stays
-- a literal prefix match rather than a single-character wildcard.
GRANT ALL PRIVILEGES ON `metacreator\_test%`.* TO 'metacreator'@'%';
FLUSH PRIVILEGES;
