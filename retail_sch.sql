"CREATE TABLE ff_sch.companies (
    inv_chk boolean DEFAULT false,
    country character varying(100) DEFAULT 'India'::character varying,
    logo_url text,
    email character varying(100),
    phone character varying(20),
    gst_no character varying(50),
    state character varying(100),
    city character varying(100),
    address text,
    address3 character varying(100),
    id integer NOT NULL DEFAULT nextval('companies_id_seq'::regclass),
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    name character varying(255) NOT NULL,
    address2 character varying(100),
    lic_no2 character varying(100),
    lic_no1 character varying(100)
);
"
"CREATE TABLE ff_sch.coupons (
    valid_to date,
    value numeric NOT NULL,
    max_usage integer DEFAULT 1,
    min_amount numeric DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    discount_type character varying(20),
    code character varying(50) NOT NULL,
    is_active boolean DEFAULT true,
    created_by integer,
    valid_from date,
    id integer NOT NULL DEFAULT nextval('coupons_id_seq'::regclass),
    used_count integer DEFAULT 0
);
"
"CREATE TABLE ff_sch.discounts (
    min_amount numeric DEFAULT 0,
    value numeric NOT NULL,
    valid_from date,
    valid_to date,
    created_by integer,
    max_discount numeric DEFAULT 0,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id integer NOT NULL DEFAULT nextval('discounts_id_seq'::regclass),
    name character varying(100) NOT NULL,
    discount_type character varying(20)
);
"
"CREATE TABLE ff_sch.fiscal_years (
    year_name character varying(20) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_active boolean DEFAULT false,
    id integer NOT NULL DEFAULT nextval('fiscal_years_id_seq'::regclass),
    end_date date NOT NULL,
    start_date date NOT NULL
);
"
"CREATE TABLE ff_sch.invoice_items (
    cartons integer NOT NULL,
    rate numeric NOT NULL,
    carton_contents character varying(255),
    product_id integer NOT NULL,
    invoice_id integer NOT NULL,
    carton_from integer,
    uom character varying(20),
    qty numeric,
    total_amount numeric NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id integer NOT NULL DEFAULT nextval('invoice_items_id_seq'::regclass),
    carton_to integer
);
"
"CREATE TABLE ff_sch.invoices (
    dispatch_from character varying(255),
    discount_type character varying(10),
    invoice_date date NOT NULL,
    igst_percent numeric DEFAULT 0,
    cgst_percent numeric DEFAULT 0,
    invoice_no integer NOT NULL,
    id integer NOT NULL DEFAULT nextval('invoices_id_seq'::regclass),
    p_address character varying(200),
    tax_percentage numeric DEFAULT 0,
    p_gst character varying(20),
    p_state character varying(30),
    taxable_amount numeric DEFAULT 0,
    created_by integer NOT NULL,
    p_place character varying(30),
    discount_amount numeric DEFAULT 0,
    sgst_percent numeric DEFAULT 0,
    transport_gst character varying(100),
    transport_name character varying(255),
    vehicle_no character varying(50),
    eway_bill_no character varying(100),
    hsn_code character varying(50),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    net_amount numeric DEFAULT 0,
    discount numeric DEFAULT 0,
    tax_type character varying(10),
    dispatch_through character varying(255),
    discount_value numeric DEFAULT 0,
    party_id integer NOT NULL,
    fiscal_year_id integer NOT NULL,
    round_off numeric,
    total_tax numeric DEFAULT 0,
    igst_amount numeric DEFAULT 0,
    cgst_amount numeric DEFAULT 0,
    sgst_amount numeric DEFAULT 0
);
"
"CREATE TABLE ff_sch.master (
    igst integer,
    sgst integer,
    id integer NOT NULL DEFAULT nextval('master_id_seq'::regclass),
    hsn_code character varying,
    is_active boolean DEFAULT false,
    cgst integer
);
"
"CREATE TABLE ff_sch.parties (
    party_id character varying(50) NOT NULL,
    id integer NOT NULL DEFAULT nextval('parties_id_seq'::regclass),
    country character varying(100) DEFAULT 'India'::character varying,
    is_active boolean DEFAULT true,
    phone character varying(20),
    city character varying(100),
    gst_no character varying(50),
    address_line2 text,
    address_line1 text,
    name character varying(255) NOT NULL,
    address text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    email character varying(100),
    state character varying(100),
    district character varying(100),
    pin_code character varying(8)
);
"
"CREATE TABLE ff_sch.product_categories (
    description text,
    name character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id integer NOT NULL DEFAULT nextval('product_categories_id_seq'::regclass)
);
"
"CREATE TABLE ff_sch.products (
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    product_id character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    uom character varying(20) NOT NULL,
    carton_contents character varying(255),
    per_box_pieces character varying(100) DEFAULT 1,
    rate numeric NOT NULL,
    category_id integer,
    fiscal_year_id integer NOT NULL,
    id integer NOT NULL DEFAULT nextval('products_id_seq'::regclass),
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);
"
"CREATE TABLE ff_sch.roles (
    name character varying(50) NOT NULL,
    permissions jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id integer NOT NULL DEFAULT nextval('roles_id_seq'::regclass)
);
"
"CREATE TABLE ff_sch.tax_summary (
    id integer NOT NULL DEFAULT nextval('tax_summary_id_seq'::regclass),
    sgst numeric DEFAULT 0,
    invoice_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    total_tax numeric DEFAULT 0,
    igst numeric DEFAULT 0,
    cgst numeric DEFAULT 0
);
"
"CREATE TABLE ff_sch.temp_inv (
    party_id character varying(150) NOT NULL,
    invoice_date date NOT NULL,
    p_address character varying(200),
    p_gst character varying(20),
    p_state character varying(30),
    p_place character varying(30),
    total_tax numeric DEFAULT 0,
    igst_amount numeric DEFAULT 0,
    cgst_amount numeric DEFAULT 0,
    sgst_amount numeric DEFAULT 0,
    igst_percent numeric DEFAULT 0,
    cgst_percent numeric DEFAULT 0,
    sgst_percent numeric DEFAULT 0,
    invoice_no integer NOT NULL,
    fiscal_year_id integer NOT NULL,
    created_by integer NOT NULL,
    eway_bill_no character varying(100),
    vehicle_no character varying(50),
    discount_amount numeric DEFAULT 0,
    discount_value numeric DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    net_amount numeric DEFAULT 0,
    transport_name character varying(255),
    round_off numeric,
    discount numeric DEFAULT 0,
    tax_percentage numeric DEFAULT 0,
    id integer NOT NULL DEFAULT nextval('temp_inv_id_seq'::regclass),
    taxable_amount numeric DEFAULT 0,
    transport_gst character varying(100),
    hsn_code character varying(50),
    dispatch_from character varying(255),
    discount_type character varying(10),
    tax_type character varying(10),
    dispatch_through character varying(255)
);
"
"CREATE TABLE ff_sch.temp_inv_items (
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    product_id integer NOT NULL,
    carton_contents character varying(255),
    uom character varying(20),
    invoice_id integer NOT NULL,
    rate numeric NOT NULL,
    cartons integer NOT NULL,
    total_amount numeric NOT NULL,
    carton_from integer,
    carton_to integer,
    qty numeric,
    id integer NOT NULL DEFAULT nextval('temp_inv_items_id_seq'::regclass)
);
"
"CREATE TABLE ff_sch.transport (
    gst_no character varying(50),
    name character varying(255) NOT NULL,
    id integer NOT NULL DEFAULT nextval('transport_id_seq'::regclass),
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_active boolean DEFAULT true
);
"
"CREATE TABLE ff_sch.users (
    username character varying(50) NOT NULL,
    role_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_active boolean DEFAULT true,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id integer NOT NULL DEFAULT nextval('users_id_seq'::regclass),
    full_name character varying(100),
    email character varying(100),
    password character varying(255) NOT NULL
);
"