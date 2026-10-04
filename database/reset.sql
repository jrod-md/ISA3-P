DROP DATABASE IF EXISTS bus_meta;
DROP DATABASE IF EXISTS market_alpha;
DROP DATABASE IF EXISTS market_beta;
DROP DATABASE IF EXISTS market_gamma;
SOURCE database/001_create_databases.sql;
SOURCE database/002_create_tables.sql;
SOURCE database/003_seed_products.sql;

