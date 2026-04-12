# AlgoMeal System Class Diagram (ERD view)

Here is the exhaustive text representation of the system architecture mapped as a Mermaid Entity-Relationship/Class Diagram. 

This diagram captures the data properties of each entity and their respective cardinalities and relationships (such as one-to-many bonds between `student` and `meal_plan` and `nutritional_record`).

```mermaid
erDiagram
    users {
        int user_id PK
        varchar faculty_name
        varchar password_hash
        enum role "Admin, Faculty"
    }

    student {
        varchar student_id PK
        varchar last_name
        varchar first_name
        enum sex
        date birth_date
        varchar grade_level
        varchar section
        decimal min_target_weight
        decimal max_target_weight
        tinyint is_4ps_beneficiary
        tinyint deworming_status
    }

    nutritional_record {
        int record_id PK
        varchar student_id FK
        int created_by FK
        decimal height
        decimal weight
        decimal bmi
        varchar nutritional_status
        date assessment_date
    }

    dietary_restrictions {
        int restriction_id PK
        varchar restriction_name
        enum type "Allergy, Religious"
    }

    student_allergy_map {
        varchar student_id PK, FK
        int restriction_id PK, FK
    }

    recipes {
        varchar recipe_id PK
        varchar recipe_name
        text description
        int energy_kcal
        decimal protein_g
        decimal carbs_g
        decimal fat_g
        decimal base_cost_per_serving
        varchar prep_time
        int servings
        varchar hex_color
    }

    recipe_allergen_tags {
        varchar recipe_id PK, FK
        int restriction_id PK, FK
    }

    recipe_ingredients {
        int ingredient_id PK
        varchar recipe_id FK
        varchar name
        varchar amount
        varchar unit
    }

    recipe_instructions {
        int instruction_id PK
        varchar recipe_id FK
        int step_no
        text instruction
    }

    daily_meal_plans {
        date scheduled_date PK
        varchar meal_a_recipe_id FK
        varchar meal_b_recipe_id FK
        tinyint is_served
    }

    meal_plan {
        int plan_id PK
        varchar student_id FK
        varchar recipe_id FK
        date scheduled_date
        decimal actual_cost
        enum feeding_status "Served, Absent"
    }

    kitchen_documentation {
        int id PK
        varchar photo_path
        date tagged_date
        varchar caption
        int uploaded_by FK
        timestamp created_at
    }

    budget_logs {
        int id PK
        decimal amount
        varchar category
        text description
        timestamp created_at
    }

    settings {
        varchar setting_key PK
        varchar setting_value
    }

    %% Relationships
    users ||--o{ nutritional_record : "creates"
    users ||--o{ kitchen_documentation : "uploads"

    student ||--o{ nutritional_record : "has"
    student ||--o{ meal_plan : "attends"
    student ||--o{ student_allergy_map : "has"
    
    dietary_restrictions ||--o{ student_allergy_map : "maps to"
    dietary_restrictions ||--o{ recipe_allergen_tags : "tags"

    recipes ||--o{ recipe_allergen_tags : "has"
    recipes ||--o{ recipe_ingredients : "contains"
    recipes ||--o{ recipe_instructions : "requires"
    recipes ||--o{ daily_meal_plans : "serves as meal A/B"
    recipes ||--o{ meal_plan : "consumed in"
    
    daily_meal_plans ||--o{ meal_plan : "dictates"
```

Because AlgoMeal relies heavily on Procedural PHP paired with MySQL, this relational database structure serves as your actual definitive structural schema for all data models.
