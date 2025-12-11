-- Indexes for optimizing stock calculation performance
-- These indexes will speed up calculateStockTotals() queries

-- Index on orders.agent_no for filtering orders by agent
CREATE INDEX IF NOT EXISTS idx_orders_agent_no ON orders(agent_no);

-- Index on orders.type for filtering by order type (DO/INV)
CREATE INDEX IF NOT EXISTS idx_orders_type ON orders(type);

-- Composite index on orders(agent_no, type) for combined filtering
CREATE INDEX IF NOT EXISTS idx_orders_agent_type ON orders(agent_no, type);

-- Index on order_items.order_id for joining items to orders
-- (This might already exist as foreign key, but ensure it's indexed)
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);

-- Index on order_items.product_no for filtering items by product
CREATE INDEX IF NOT EXISTS idx_order_items_product_no ON order_items(product_no);

-- Composite index on order_items(order_id, product_no) for efficient join + filter
CREATE INDEX IF NOT EXISTS idx_order_items_order_product ON order_items(order_id, product_no);

-- Composite index on order_items(agent_no, product_no) if agent_no is stored in order_items
-- (Check if order_items has agent_no column, if not, skip this)
-- CREATE INDEX IF NOT EXISTS idx_order_items_agent_product ON order_items(agent_no, product_no);

-- Indexes for item_transactions table (if exists)
-- Composite index on item_transactions(agent_no, ITEMNO) for efficient filtering
CREATE INDEX IF NOT EXISTS idx_item_transactions_agent_itemno ON item_transactions(agent_no, ITEMNO);

-- Index on item_transactions.agent_no
CREATE INDEX IF NOT EXISTS idx_item_transactions_agent_no ON item_transactions(agent_no);

-- Index on item_transactions.ITEMNO
CREATE INDEX IF NOT EXISTS idx_item_transactions_itemno ON item_transactions(ITEMNO);

-- Index on item_transactions.transaction_type for filtering by type
CREATE INDEX IF NOT EXISTS idx_item_transactions_type ON item_transactions(transaction_type);
