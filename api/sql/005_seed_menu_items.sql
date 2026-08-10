-- Menu items seed (matches app dummy menus)
USE foodmitra;

SET @img_tea = 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=600&q=80';
SET @img_lemon = 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=600&q=80';
SET @img_soda = 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80';
SET @img_snack = 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=600&q=80';
SET @img_dessert = 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=600&q=80';
SET @img_burger = 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=600&q=80';
SET @img_meal = 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=600&q=80';
SET @img_salad = 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80';
SET @img_paneer = 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?auto=format&fit=crop&w=600&q=80';
SET @img_dosa = 'https://images.unsplash.com/photo-1668236543090-82eba5ee5976?auto=format&fit=crop&w=600&q=80';

-- Hotel 1
INSERT INTO menu_items (public_id, hotel_id, category_id, name, description, price, image, is_veg, is_recommended, sort_order)
SELECT '1-ginger-lemon', 1, c.id, 'Ginger Lemon Tea', 'Zesty ginger and fresh lemon steeped into a warming cup.', 89, @img_lemon, 1, 1, 1 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='tea'
UNION ALL SELECT '1-masala-chai', 1, c.id, 'Masala Chai', 'Classic Indian chai with aromatic spices.', 49, @img_tea, 1, 1, 2 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='tea'
UNION ALL SELECT '1-green-tea', 1, c.id, 'Green Tea', 'Light and refreshing green tea.', 69, @img_tea, 1, 0, 3 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='tea'
UNION ALL SELECT '1-elaichi', 1, c.id, 'Elaichi Tea', 'Fragrant cardamom tea.', 59, @img_tea, 1, 0, 4 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='tea'
UNION ALL SELECT '1-fresh-lime', 1, c.id, 'Fresh Lime Soda', 'Sparkling soda with freshly squeezed lime.', 79, @img_soda, 1, 1, 5 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='drinks'
UNION ALL SELECT '1-mango-shake', 1, c.id, 'Mango Shake', 'Thick mango milkshake from ripe pulp.', 119, @img_soda, 1, 0, 6 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='drinks'
UNION ALL SELECT '1-buttermilk', 1, c.id, 'Salted Buttermilk', 'Cool salted chaas with cumin.', 49, @img_soda, 1, 0, 7 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='drinks'
UNION ALL SELECT '1-samosa', 1, c.id, 'Veg Samosa (2 pcs)', 'Crispy pastry with spiced potato and peas.', 60, @img_snack, 1, 1, 8 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='snacks'
UNION ALL SELECT '1-pakora', 1, c.id, 'Mix Veg Pakora', 'Assorted vegetable fritters with chutney.', 99, @img_snack, 1, 0, 9 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='snacks'
UNION ALL SELECT '1-sandwich', 1, c.id, 'Grilled Veg Sandwich', 'Toasted sandwich with fresh veggies.', 129, @img_snack, 1, 0, 10 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='snacks'
UNION ALL SELECT '1-brownie', 1, c.id, 'Chocolate Brownie', 'Fudgy chocolate brownie.', 149, @img_dessert, 1, 0, 11 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='desserts'
UNION ALL SELECT '1-icecream', 1, c.id, 'Vanilla Ice Cream', 'Rich creamy vanilla ice cream.', 89, @img_dessert, 1, 0, 12 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='desserts'
UNION ALL SELECT '1-gulab', 1, c.id, 'Gulab Jamun (2 pcs)', 'Soft dumplings in rose-cardamom syrup.', 70, @img_dessert, 1, 0, 13 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='desserts'
UNION ALL SELECT '1-thali', 1, c.id, 'Chef Special Thali', 'Curry, dal, rice, roti and sides.', 199, @img_burger, 1, 1, 14 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='mains'
UNION ALL SELECT '1-combo', 1, c.id, 'Meal Combo', 'Main with a drink combo.', 179, @img_burger, 1, 0, 15 FROM menu_categories c WHERE c.hotel_id=1 AND c.slug='mains';

-- Hotel 2
INSERT INTO menu_items (public_id, hotel_id, category_id, name, description, price, image, is_veg, is_recommended, sort_order)
SELECT '2-ginger-lemon', 2, c.id, 'Ginger Lemon Tea', 'Zesty ginger and fresh lemon.', 89, @img_lemon, 1, 1, 1 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='tea'
UNION ALL SELECT '2-masala-chai', 2, c.id, 'Masala Chai', 'Classic spiced chai.', 49, @img_tea, 1, 1, 2 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='tea'
UNION ALL SELECT '2-fresh-lime', 2, c.id, 'Fresh Lime Soda', 'Crisp lime soda.', 79, @img_soda, 1, 1, 3 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='drinks'
UNION ALL SELECT '2-samosa', 2, c.id, 'Veg Samosa (2 pcs)', 'Crispy spiced samosas.', 60, @img_snack, 1, 1, 4 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='snacks'
UNION ALL SELECT '2-brownie', 2, c.id, 'Chocolate Brownie', 'Fudgy brownie.', 149, @img_dessert, 1, 0, 5 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='desserts'
UNION ALL SELECT '2-thali', 2, c.id, 'Chef Special Thali', 'Wholesome North Indian thali.', 199, @img_meal, 1, 1, 6 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='mains'
UNION ALL SELECT '2-combo', 2, c.id, 'Meal Combo', 'Main with drink.', 179, @img_meal, 1, 0, 7 FROM menu_categories c WHERE c.hotel_id=2 AND c.slug='mains';

-- Hotel 3
INSERT INTO menu_items (public_id, hotel_id, category_id, name, description, price, image, is_veg, is_recommended, sort_order)
SELECT '3-ginger-lemon', 3, c.id, 'Ginger Lemon Tea', 'Zesty ginger lemon tea.', 89, @img_lemon, 1, 1, 1 FROM menu_categories c WHERE c.hotel_id=3 AND c.slug='tea'
UNION ALL SELECT '3-fresh-lime', 3, c.id, 'Fresh Lime Soda', 'Sparkling lime soda.', 79, @img_soda, 1, 1, 2 FROM menu_categories c WHERE c.hotel_id=3 AND c.slug='drinks'
UNION ALL SELECT '3-salad', 3, c.id, 'Garden Bowl', 'Fresh mixed salad bowl.', 149, @img_salad, 1, 1, 3 FROM menu_categories c WHERE c.hotel_id=3 AND c.slug='mains'
UNION ALL SELECT '3-combo', 3, c.id, 'Meal Combo', 'Bowl with a drink.', 179, @img_salad, 1, 0, 4 FROM menu_categories c WHERE c.hotel_id=3 AND c.slug='mains';

-- Hotel 4
INSERT INTO menu_items (public_id, hotel_id, category_id, name, description, price, image, is_veg, is_recommended, sort_order)
SELECT '4-masala-chai', 4, c.id, 'Masala Chai', 'Spiced chai.', 49, @img_tea, 1, 1, 1 FROM menu_categories c WHERE c.hotel_id=4 AND c.slug='tea'
UNION ALL SELECT '4-samosa', 4, c.id, 'Veg Samosa (2 pcs)', 'Hot samosas.', 60, @img_snack, 1, 1, 2 FROM menu_categories c WHERE c.hotel_id=4 AND c.slug='snacks'
UNION ALL SELECT '4-paneer', 4, c.id, 'Paneer Butter Masala', 'Creamy paneer curry.', 220, @img_paneer, 1, 1, 3 FROM menu_categories c WHERE c.hotel_id=4 AND c.slug='mains'
UNION ALL SELECT '4-thali', 4, c.id, 'Paneer Thali', 'Paneer special thali.', 249, @img_paneer, 1, 1, 4 FROM menu_categories c WHERE c.hotel_id=4 AND c.slug='mains';

-- Hotel 5
INSERT INTO menu_items (public_id, hotel_id, category_id, name, description, price, image, is_veg, is_recommended, sort_order)
SELECT '5-masala-chai', 5, c.id, 'Filter Coffee', 'South Indian filter coffee.', 49, @img_tea, 1, 1, 1 FROM menu_categories c WHERE c.hotel_id=5 AND c.slug='tea'
UNION ALL SELECT '5-fresh-lime', 5, c.id, 'Fresh Lime Soda', 'Tangy lime soda.', 79, @img_soda, 1, 0, 2 FROM menu_categories c WHERE c.hotel_id=5 AND c.slug='drinks'
UNION ALL SELECT '5-dosa', 5, c.id, 'Masala Dosa', 'Crispy dosa with potato masala.', 129, @img_dosa, 1, 1, 3 FROM menu_categories c WHERE c.hotel_id=5 AND c.slug='mains'
UNION ALL SELECT '5-combo', 5, c.id, 'Meal Combo', 'Dosa meal combo.', 179, @img_dosa, 1, 0, 4 FROM menu_categories c WHERE c.hotel_id=5 AND c.slug='mains';
