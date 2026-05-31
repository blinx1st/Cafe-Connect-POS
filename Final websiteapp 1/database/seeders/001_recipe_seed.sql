USE cafe_connect_crm;

UPDATE inventory_materials
SET unit_cost = CASE material_name
    WHEN 'Arabica beans' THEN 190000
    WHEN 'Robusta beans' THEN 120000
    WHEN 'Fresh milk' THEN 30000
    WHEN 'Tea leaves' THEN 160000
    WHEN 'Croissant dough' THEN 25000
    ELSE unit_cost
END;

INSERT INTO recipes (product_id, recipe_name, yield_quantity, status) VALUES
(1, 'Signature Brown Latte recipe', 1, 'active'),
(2, 'Vietnamese Phin Coffee recipe', 1, 'active'),
(3, 'Cold Brew Citrus recipe', 1, 'active'),
(4, 'Lotus Oolong Tea recipe', 1, 'active'),
(5, 'Peach Lemongrass Tea recipe', 1, 'active'),
(6, 'Mango Yogurt Smoothie recipe', 1, 'active'),
(7, 'Croissant Butter recipe', 1, 'active'),
(8, 'Tiramisu Cup recipe', 1, 'active'),
(9, 'May Bloom Macchiato recipe', 1, 'active');

INSERT INTO recipe_items (recipe_id, material_id, quantity_per_unit) VALUES
((SELECT id FROM recipes WHERE product_id = 1), (SELECT id FROM inventory_materials WHERE material_name = 'Arabica beans'), 0.0180),
((SELECT id FROM recipes WHERE product_id = 1), (SELECT id FROM inventory_materials WHERE material_name = 'Fresh milk'), 0.1800),
((SELECT id FROM recipes WHERE product_id = 2), (SELECT id FROM inventory_materials WHERE material_name = 'Robusta beans'), 0.0200),
((SELECT id FROM recipes WHERE product_id = 3), (SELECT id FROM inventory_materials WHERE material_name = 'Arabica beans'), 0.0200),
((SELECT id FROM recipes WHERE product_id = 4), (SELECT id FROM inventory_materials WHERE material_name = 'Tea leaves'), 0.0100),
((SELECT id FROM recipes WHERE product_id = 5), (SELECT id FROM inventory_materials WHERE material_name = 'Tea leaves'), 0.0120),
((SELECT id FROM recipes WHERE product_id = 6), (SELECT id FROM inventory_materials WHERE material_name = 'Fresh milk'), 0.1200),
((SELECT id FROM recipes WHERE product_id = 7), (SELECT id FROM inventory_materials WHERE material_name = 'Croissant dough'), 1.0000),
((SELECT id FROM recipes WHERE product_id = 8), (SELECT id FROM inventory_materials WHERE material_name = 'Fresh milk'), 0.0800),
((SELECT id FROM recipes WHERE product_id = 9), (SELECT id FROM inventory_materials WHERE material_name = 'Arabica beans'), 0.0180),
((SELECT id FROM recipes WHERE product_id = 9), (SELECT id FROM inventory_materials WHERE material_name = 'Fresh milk'), 0.1500);

INSERT INTO website_orders (invoice_id, customer_id, fulfillment_type, order_status, delivery_address, customer_note, requested_at, created_at)
SELECT id,
       customer_id,
       CASE WHEN sales_channel = 'delivery' THEN 'delivery' ELSE 'pickup' END,
       'completed',
       CASE WHEN sales_channel = 'delivery' THEN 'Sample delivery address' ELSE NULL END,
       'Seeded from sample invoice',
       paid_at,
       created_at
FROM invoices
WHERE sales_channel IN ('website', 'delivery');
