-- Run once in phpMyAdmin if you already imported server_schema.sql before this column existed.
USE server_schema;

ALTER TABLE Installations
    ADD COLUMN packageFingerprint VARCHAR(64) NULL AFTER packageVersion;
