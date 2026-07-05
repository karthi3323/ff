CREATE DATABASE billing_software;
\c billing_software;

-- Companies table
CREATE TABLE ff_sch.companies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'India',
    gst_no VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    logo_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles table
CREATE TABLE ff_sch.roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE ff_sch.users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role_id INTEGER NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES ff_sch.roles(id) ON DELETE RESTRICT
);

-- Fiscal years table
CREATE TABLE ff_sch.fiscal_years (
    id SERIAL PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (start_date, end_date)
);

-- Parties table (Customers)
CREATE TABLE ff_sch.parties (
    id SERIAL PRIMARY KEY,
    party_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    address_line1 TEXT,
    address_line2 TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'India',
    gst_no VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product categories table
CREATE TABLE ff_sch.product_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE ff_sch.products (
    id SERIAL PRIMARY KEY,
    product_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    hsn_code VARCHAR(50),
    uom VARCHAR(20) NOT NULL,
    per_box_pieces INTEGER DEFAULT 1 CHECK (per_box_pieces > 0),
    category_id INTEGER,
    is_active BOOLEAN DEFAULT true,
    fiscal_year_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES ff_sch.product_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (fiscal_year_id) REFERENCES ff_sch.fiscal_years(id) ON DELETE RESTRICT
);

-- This table stores price codes, which act as different price lists (e.g., Retail, Wholesale).
CREATE TABLE ff_sch.price_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    fiscal_year_id INTEGER NOT NULL,
    is_active BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- A price code should be unique within a fiscal year.
    CONSTRAINT uq_price_codes_fiscal_year_id_code UNIQUE (fiscal_year_id, code),
    
    -- Foreign key to the fiscal_years table to maintain integrity.
    FOREIGN KEY (fiscal_year_id) REFERENCES ff_sch.fiscal_years(id) ON DELETE RESTRICT
);

-- This table links products to price codes, allowing for multiple prices per product.
-- This replaces the single `rate` column on the `products` table.
CREATE TABLE ff_sch.product_prices (
    product_id INTEGER NOT NULL,
    price_code_id INTEGER NOT NULL,
    rate DECIMAL(10, 2) NOT NULL CHECK (rate >= 0),
    PRIMARY KEY (product_id, price_code_id),
    FOREIGN KEY (product_id) REFERENCES ff_sch.products(id) ON DELETE CASCADE,
    FOREIGN KEY (price_code_id) REFERENCES ff_sch.price_codes(id) ON DELETE CASCADE
);

-- Invoices table
CREATE TABLE ff_sch.invoices (
    id SERIAL PRIMARY KEY,
    invoice_no VARCHAR(100) UNIQUE NOT NULL,
    party_id INTEGER NOT NULL,
    price_code_id INTEGER,
    dispatch_through VARCHAR(255),
    invoice_date DATE NOT NULL,
    taxable_amount DECIMAL(10,2) DEFAULT 0 CHECK (taxable_amount >= 0),
    tax_type VARCHAR(10) CHECK (tax_type IN ('GST', 'VAT', 'NONE')),
    tax_percentage DECIMAL(5,2) DEFAULT 0 CHECK (tax_percentage >= 0),
    discount DECIMAL(10,2) DEFAULT 0 CHECK (discount >= 0),
    net_amount DECIMAL(10,2) DEFAULT 0 CHECK (net_amount >= 0),
    fiscal_year_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (party_id) REFERENCES ff_sch.parties(id) ON DELETE RESTRICT,
    FOREIGN KEY (fiscal_year_id) REFERENCES ff_sch.fiscal_years(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES ff_sch.users(id) ON DELETE RESTRICT,
    FOREIGN KEY (price_code_id) REFERENCES ff_sch.price_codes(id) ON DELETE SET NULL
);

-- Invoice items table
CREATE TABLE ff_sch.invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    hsn_code VARCHAR(50),
    uom VARCHAR(20),
    rate DECIMAL(10,2) NOT NULL CHECK (rate >= 0),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    total_amount DECIMAL(10,2) NOT NULL CHECK (total_amount >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ff_sch.invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES ff_sch.products(id) ON DELETE RESTRICT
);

-- Tax summary table
CREATE TABLE ff_sch.tax_summary (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER NOT NULL UNIQUE,
    sgst DECIMAL(10,2) DEFAULT 0 CHECK (sgst >= 0),
    cgst DECIMAL(10,2) DEFAULT 0 CHECK (cgst >= 0),
    igst DECIMAL(10,2) DEFAULT 0 CHECK (igst >= 0),
    total_tax DECIMAL(10,2) DEFAULT 0 CHECK (total_tax >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ff_sch.invoices(id) ON DELETE CASCADE
);

-- Discounts table
CREATE TABLE ff_sch.discounts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    discount_type VARCHAR(20) CHECK (discount_type IN ('percentage', 'fixed')),
    value DECIMAL(10,2) NOT NULL CHECK (value >= 0),
    min_amount DECIMAL(10,2) DEFAULT 0 CHECK (min_amount >= 0),
    max_discount DECIMAL(10,2) DEFAULT 0 CHECK (max_discount >= 0),
    is_active BOOLEAN DEFAULT true,
    valid_from DATE,
    valid_to DATE,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES ff_sch.users(id) ON DELETE SET NULL
);

-- Coupons table
CREATE TABLE ff_sch.coupons (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type VARCHAR(20) CHECK (discount_type IN ('percentage', 'fixed')),
    value DECIMAL(10,2) NOT NULL CHECK (value >= 0),
    min_amount DECIMAL(10,2) DEFAULT 0 CHECK (min_amount >= 0),
    max_usage INTEGER DEFAULT 1 CHECK (max_usage > 0),
    used_count INTEGER DEFAULT 0 CHECK (used_count >= 0),
    is_active BOOLEAN DEFAULT true,
    valid_from DATE,
    valid_to DATE,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES ff_sch.users(id) ON DELETE SET NULL
);

-- Receipt Header table
CREATE TABLE ff_sch.rcpt_hdr (
    id SERIAL PRIMARY KEY,
    receipt_no VARCHAR(50) NOT NULL,
    receipt_date DATE NOT NULL,
    agent_name VARCHAR(255),
    total_receipt_amount DECIMAL(12, 2) NOT NULL,
    fiscal_year_id INTEGER NOT NULL,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (receipt_no, fiscal_year_id),
    FOREIGN KEY (fiscal_year_id) REFERENCES ff_sch.fiscal_years(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES ff_sch.users(id) ON DELETE SET NULL
);

-- Receipt Details table
CREATE TABLE ff_sch.rcpt_dtl (
    id SERIAL PRIMARY KEY,
    rcpt_hdr_id INTEGER NOT NULL,
    estimate_id INTEGER NOT NULL,
    receipt_amount DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rcpt_hdr_id) REFERENCES ff_sch.rcpt_hdr(id) ON DELETE CASCADE,
    FOREIGN KEY (estimate_id) REFERENCES ff_sch.estimate(id) ON DELETE RESTRICT
);

-- Audit log table for tracking changes
CREATE TABLE ff_sch.audit_logs (
    id SERIAL PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INTEGER NOT NULL,
    action VARCHAR(10) NOT NULL CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
    old_values JSONB,
    new_values JSONB,
    user_id INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES ff_sch.users(id) ON DELETE SET NULL
);

-- Indexes for better performance
CREATE INDEX idx_invoices_party_id ON ff_sch.invoices(party_id);
CREATE INDEX idx_invoices_date ON ff_sch.invoices(invoice_date);
CREATE INDEX idx_invoices_fiscal_year ON ff_sch.invoices(fiscal_year_id);
CREATE INDEX idx_products_category ON ff_sch.products(category_id);
CREATE INDEX idx_products_fiscal_year ON ff_sch.products(fiscal_year_id);
CREATE INDEX idx_invoice_items_invoice ON ff_sch.invoice_items(invoice_id);
CREATE INDEX idx_invoice_items_product ON ff_sch.invoice_items(product_id);
CREATE INDEX idx_parties_name ON ff_sch.parties(name);
CREATE INDEX idx_products_name ON ff_sch.products(name);
CREATE INDEX idx_rcpt_hdr_agent_name ON ff_sch.rcpt_hdr(agent_name);
CREATE INDEX idx_rcpt_hdr_receipt_date ON ff_sch.rcpt_hdr(receipt_date);
CREATE INDEX idx_rcpt_dtl_rcpt_hdr_id ON ff_sch.rcpt_dtl(rcpt_hdr_id);
CREATE INDEX idx_rcpt_dtl_estimate_id ON ff_sch.rcpt_dtl(estimate_id);


-- Insert default data
INSERT INTO ff_sch.companies (name, address, city, state, country, gst_no, phone, email) 
VALUES ('Retail Store', '123 Main Street', 'Mumbai', 'Maharashtra', 'India', 'GST123456789', '9876543210', 'info@retailstore.com');

INSERT INTO ff_sch.roles (name, permissions) 
VALUES 
    ('Admin', '{"all": true}'), 
    ('Manager', '{"read": true, "write": true, "reports": true}'),
    ('User', '{"read": true, "write": true}');

INSERT INTO ff_sch.users (username, password, email, full_name, role_id) 
VALUES 
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@store.com', 'Administrator', 1),
    ('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager@store.com', 'Store Manager', 2),
    ('user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user@store.com', 'Sales User', 3);

INSERT INTO ff_sch.fiscal_years (year_name, start_date, end_date, is_active) 
VALUES 
    ('2023-24', '2023-04-01', '2024-03-31', false),
    ('2024-25', '2024-04-01', '2025-03-31', true);

INSERT INTO ff_sch.product_categories (name, description) 
VALUES 
    ('General', 'General products'),
    ('Electronics', 'Electronic items and gadgets'),
    ('Clothing', 'Apparel and clothing items'),
    ('Food', 'Food products and groceries'),
    ('Stationery', 'Office and school stationery');

-- Sample parties
INSERT INTO ff_sch.parties (party_id, name, address_line1, city, state, gst_no, phone) 
VALUES 
    ('PTY-2024-001', 'ABC Traders', '45 Market Street', 'Mumbai', 'Maharashtra', 'GSTABC123456', '9876543210'),
    ('PTY-2024-002', 'XYZ Enterprises', '78 Business Park', 'Pune', 'Maharashtra', 'GSTXYZ654321', '9876543211'),
    ('PTY-2024-003', 'Global Imports', '23 Trade Center', 'Delhi', 'Delhi', 'GSTGLB987654', '9876543212');

-- Sample products
INSERT INTO ff_sch.products (product_id, name, hsn_code, uom, category_id, fiscal_year_id) 
VALUES 
    ('PROD-2024-001', 'Laptop Dell Inspiron', '84713000', 'PCS', 2, 2),
    ('PROD-2024-002', 'Office Chair', '94013000', 'PCS', 1, 2),
    ('PROD-2024-003', 'Notebook Pack', '48201000', 'PKT', 5, 2),
    ('PROD-2024-004', 'Wireless Mouse', '84716070', 'PCS', 2, 2);

-- Sample Price Codes for the active fiscal year (ID=2)
INSERT INTO ff_sch.price_codes (code, name, fiscal_year_id, is_active)
VALUES
    ('RETAIL-24', 'Retail Price 2024-25', 2, true),
    ('WHS-24', 'Wholesale Price 2024-25', 2, true);

-- Sample Product Prices linking products to price codes
INSERT INTO ff_sch.product_prices (product_id, price_code_id, rate)
VALUES
    (1, 1, 45000.00), (1, 2, 42000.00), -- Laptop
    (2, 1, 3500.00), (2, 2, 3200.00),  -- Office Chair
    (3, 1, 250.00), (3, 2, 220.00),   -- Notebook
    (4, 1, 800.00);                   -- Wireless Mouse (only has retail price)

-- Create functions for automatic updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_companies_updated_at BEFORE UPDATE ON ff_sch.companies FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON ff_sch.users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_parties_updated_at BEFORE UPDATE ON ff_sch.parties FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON ff_sch.products FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_price_codes_updated_at BEFORE UPDATE ON ff_sch.price_codes FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_rcpt_hdr_updated_at BEFORE UPDATE ON ff_sch.rcpt_hdr FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function for audit logging
CREATE OR REPLACE FUNCTION audit_trigger()
RETURNS TRIGGER AS $$
DECLARE
    old_data JSONB;
    new_data JSONB;
BEGIN
    IF TG_OP = 'DELETE' THEN
        old_data = row_to_json(OLD);
        INSERT INTO audit_logs (table_name, record_id, action, old_values, user_id)
        VALUES (TG_TABLE_NAME, OLD.id, TG_OP, old_data, current_setting('app.user_id', TRUE)::INTEGER);
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        old_data = row_to_json(OLD);
        new_data = row_to_json(NEW);
        INSERT INTO audit_logs (table_name, record_id, action, old_values, new_values, user_id)
        VALUES (TG_TABLE_NAME, NEW.id, TG_OP, old_data, new_data, current_setting('app.user_id', TRUE)::INTEGER);
        RETURN NEW;
    ELSIF TG_OP = 'INSERT' THEN
        new_data = row_to_json(NEW);
        INSERT INTO audit_logs (table_name, record_id, action, new_values, user_id)
        VALUES (TG_TABLE_NAME, NEW.id, TG_OP, new_data, current_setting('app.user_id', TRUE)::INTEGER);
        RETURN NEW;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Create audit triggers for important tables
CREATE TRIGGER audit_invoices AFTER INSERT OR UPDATE OR DELETE ON ff_sch.invoices FOR EACH ROW EXECUTE FUNCTION audit_trigger();
CREATE TRIGGER audit_parties AFTER INSERT OR UPDATE OR DELETE ON ff_sch.parties FOR EACH ROW EXECUTE FUNCTION audit_trigger();
CREATE TRIGGER audit_products AFTER INSERT OR UPDATE OR DELETE ON ff_sch.products FOR EACH ROW EXECUTE FUNCTION audit_trigger();

-- Views for common queries
CREATE VIEW ff_sch.vw_invoice_details AS
SELECT 
    i.id,
    i.invoice_no,
    i.invoice_date,
    p.name as party_name,
    p.gst_no as party_gst,
    i.taxable_amount,
    i.tax_percentage,
    i.discount,
    i.net_amount,
    i.dispatch_through,
    u.full_name as created_by_user,
    fy.year_name as fiscal_year
FROM ff_sch.invoices i
LEFT JOIN ff_sch.parties p ON i.party_id = p.id
LEFT JOIN ff_sch.users u ON i.created_by = u.id
LEFT JOIN ff_sch.fiscal_years fy ON i.fiscal_year_id = fy.id;

CREATE VIEW ff_sch.vw_invoice_items_details AS
SELECT 
    ii.id,
    ii.invoice_id,
    i.invoice_no,
    p.name as product_name,
    p.product_id,
    ii.hsn_code,
    ii.uom,
    ii.rate,
    ii.quantity,
    ii.total_amount,
    pc.name as category_name
FROM ff_sch.invoice_items ii
LEFT JOIN ff_sch.invoices i ON ii.invoice_id = i.id
LEFT JOIN ff_sch.products p ON ii.product_id = p.id
LEFT JOIN ff_sch.product_categories pc ON p.category_id = pc.id;

CREATE VIEW ff_sch.vw_sales_summary AS
SELECT 
    DATE(i.invoice_date) as sale_date,
    COUNT(*) as invoice_count,
    SUM(i.taxable_amount) as total_taxable,
    SUM(i.discount) as total_discount,
    SUM(i.net_amount) as total_sales,
    AVG(i.net_amount) as avg_invoice_value
FROM ff_sch.invoices i
GROUP BY DATE(i.invoice_date);