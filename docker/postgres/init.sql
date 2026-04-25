-- Initialization script for PostgreSQL
-- This runs automatically when the postgres container is first created.

-- Create the testing database (main db is created via POSTGRES_DB env var)
CREATE DATABASE fms_db_testing;
GRANT ALL PRIVILEGES ON DATABASE fms_db_testing TO fms_user;
