<?php
require_once __DIR__ . '/../db.php';

echo "Migrating daily_meal_plans...<br>\n";

$sql = "
CREATE TABLE IF NOT EXISTS `daily_meal_plans` (
  `scheduled_date` date NOT NULL,
  `meal_a_recipe_id` varchar(10) NOT NULL,
  `meal_b_recipe_id` varchar(10) NOT NULL,
  PRIMARY KEY (`scheduled_date`),
  KEY `meal_a_recipe_id` (`meal_a_recipe_id`),
  KEY `meal_b_recipe_id` (`meal_b_recipe_id`),
  CONSTRAINT `daily_meal_plans_ibfk_1` FOREIGN KEY (`meal_a_recipe_id`) REFERENCES `recipes` (`recipe_id`),
  CONSTRAINT `daily_meal_plans_ibfk_2` FOREIGN KEY (`meal_b_recipe_id`) REFERENCES `recipes` (`recipe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";
$conn->query($sql);

$query = $conn->query("SELECT scheduled_date, GROUP_CONCAT(DISTINCT recipe_id) as recipes FROM meal_plan GROUP BY scheduled_date");
while ($row = $query->fetch_assoc()) {
    $date = $row['scheduled_date'];
    $recipes = explode(',', $row['recipes']);
    $meal_a = $recipes[0];
    $meal_b = isset($recipes[1]) ? $recipes[1] : $recipes[0];

    $stmt = $conn->prepare("INSERT IGNORE INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $date, $meal_a, $meal_b);
    $stmt->execute();
}
echo "Migration completed successfully.<br>\n";
?>
