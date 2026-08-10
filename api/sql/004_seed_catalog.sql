-- Seed hotels + menus matching app dummy data
USE foodmitra;

INSERT INTO hotels (
  id, public_id, name, image, rating, rating_count, area,
  delivery_mins, distance_km, delivery_fee, avg_price, tags,
  pure_veg, offer_active, is_active, latitude, longitude, sort_order
) VALUES
(1, '1', 'The Crumbles',
 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=900&q=80',
 3.2, 66, 'Fatehgunj', 23, 5.36, 45.51, 280, 'Burger • Beverages • + 3 more',
 1, 1, 1, 22.3225200, 73.1812000, 1),
(2, '2', 'Spice Garden',
 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=900&q=80',
 4.5, 214, 'Alkapuri', 28, 3.20, 29.00, 350, 'North Indian • Thali • + 2 more',
 1, 0, 1, 22.3145000, 73.1685000, 2),
(3, '3', 'Green Bowl Cafe',
 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=80',
 4.1, 128, 'Sayajigunj', 20, 2.80, 19.50, 220, 'Salads • Healthy • + 4 more',
 1, 1, 1, 22.3102000, 73.1810000, 3),
(4, '4', 'Paneer Hub',
 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?auto=format&fit=crop&w=900&q=80',
 3.8, 97, 'Manjalpur', 32, 4.10, 35.00, 310, 'Paneer • Curries • + 3 more',
 1, 0, 1, 22.2730000, 73.1890000, 4),
(5, '5', 'Fresh Leaf Kitchen',
 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=900&q=80',
 4.3, 183, 'Karelibaug', 25, 6.00, 42.00, 190, 'South Indian • Dosas • + 2 more',
 1, 1, 1, 22.3260000, 73.1850000, 5)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  latitude = VALUES(latitude),
  longitude = VALUES(longitude);

INSERT INTO hotel_offers (hotel_id, title, subtitle, sort_order) VALUES
(1, 'Items at ₹89', 'On select items', 1),
(1, 'Flat 20% OFF', 'Above ₹299', 2),
(2, 'Items at ₹99', 'On select items', 1),
(2, 'Free delivery', 'Above ₹199', 2),
(3, 'Items at ₹79', 'On select salads', 1),
(3, 'Buy 1 Get 1', 'On drinks', 2),
(4, 'Items at ₹129', 'On paneer specials', 1),
(4, '₹50 OFF', 'Above ₹349', 2),
(5, 'Items at ₹89', 'On select dosas', 1),
(5, 'Free filter coffee', 'With any meal', 2);

-- Categories for each hotel (slug used by app chips)
INSERT INTO menu_categories (hotel_id, slug, name, icon, sort_order) VALUES
(1, 'all', 'All Items', 'all', 0),
(1, 'tea', 'Tea', 'tea', 1),
(1, 'drinks', 'Cold Drinks', 'drink', 2),
(1, 'snacks', 'Snacks', 'snack', 3),
(1, 'desserts', 'Desserts', 'dessert', 4),
(1, 'mains', 'Mains', 'meal', 5),
(2, 'all', 'All Items', 'all', 0),
(2, 'tea', 'Tea', 'tea', 1),
(2, 'drinks', 'Cold Drinks', 'drink', 2),
(2, 'snacks', 'Snacks', 'snack', 3),
(2, 'desserts', 'Desserts', 'dessert', 4),
(2, 'mains', 'Mains', 'meal', 5),
(3, 'all', 'All Items', 'all', 0),
(3, 'tea', 'Tea', 'tea', 1),
(3, 'drinks', 'Cold Drinks', 'drink', 2),
(3, 'snacks', 'Snacks', 'snack', 3),
(3, 'desserts', 'Desserts', 'dessert', 4),
(3, 'mains', 'Bowls', 'salad', 5),
(4, 'all', 'All Items', 'all', 0),
(4, 'tea', 'Tea', 'tea', 1),
(4, 'drinks', 'Cold Drinks', 'drink', 2),
(4, 'snacks', 'Snacks', 'snack', 3),
(4, 'desserts', 'Desserts', 'dessert', 4),
(4, 'mains', 'Mains', 'meal', 5),
(5, 'all', 'All Items', 'all', 0),
(5, 'tea', 'Tea', 'tea', 1),
(5, 'drinks', 'Cold Drinks', 'drink', 2),
(5, 'snacks', 'Snacks', 'snack', 3),
(5, 'desserts', 'Desserts', 'dessert', 4),
(5, 'mains', 'Mains', 'meal', 5);

-- Helper note: category_id resolved via join in seed inserts below per hotel
