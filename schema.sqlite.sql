PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS materials (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  cost_per_g REAL NOT NULL,
  build_rate_g_per_hr REAL NOT NULL,
  machine_rate_per_hr REAL NOT NULL,
  setup_fee REAL NOT NULL,
  margin_pct REAL NOT NULL DEFAULT 0.3,
  enabled INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS colors (
  id INTEGER PRIMARY KEY,
  material_id INTEGER NOT NULL REFERENCES materials(id),
  name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS operations (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  cost_per_part REAL NOT NULL DEFAULT 0,
  cost_fixed REAL NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS price_tiers (
  id INTEGER PRIMARY KEY,
  label TEXT NOT NULL,
  qty INTEGER NOT NULL,
  order_index INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS quotes (
  id INTEGER PRIMARY KEY,
  public_ref TEXT UNIQUE NOT NULL,
  material_id INTEGER,
  color_id INTEGER,
  part_weight_g REAL,
  cavities INTEGER,
  quantity INTEGER,
  operations_json TEXT,
  price_breaks_json TEXT,
  manual_required INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS files (
  id INTEGER PRIMARY KEY,
  quote_ref TEXT NOT NULL,
  name TEXT NOT NULL,
  mime TEXT,
  size_bytes INTEGER,
  path TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS leads (
  id INTEGER PRIMARY KEY,
  quote_ref TEXT NOT NULL,
  name TEXT,
  email TEXT,
  company TEXT,
  phone TEXT,
  country TEXT,
  region TEXT,
  notes TEXT,
  created_at TEXT NOT NULL
);

-- Seed data
INSERT INTO materials (id,name,cost_per_g,build_rate_g_per_hr,machine_rate_per_hr,setup_fee,margin_pct)
VALUES (1,'FDM ABS',0.05,300,25,15,0.35)
ON CONFLICT(id) DO NOTHING;

INSERT INTO colors (id,material_id,name)
VALUES (1,1,'Natural') ON CONFLICT(id) DO NOTHING;

INSERT INTO operations (id,name,cost_per_part,cost_fixed) VALUES
  (1,'Deburr',0.10,0),
  (2,'Basic QA',0.05,0)
ON CONFLICT(id) DO NOTHING;

INSERT INTO price_tiers (id,label,qty,order_index) VALUES
  (1,'Price Break 1',1,1),
  (2,'Price Break 2',10,2),
  (3,'Price Break 3',50,3),
  (4,'Price Break 4',100,4),
  (5,'Price Break 5',500,5)
ON CONFLICT(id) DO NOTHING;