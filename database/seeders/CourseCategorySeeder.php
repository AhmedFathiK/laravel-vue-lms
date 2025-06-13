<?php

namespace Database\Seeders;

use App\Models\CourseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => [
                    'en' => 'Programming',
                    'es' => 'Programación',
                    'fr' => 'Programmation',
                ],
                'description' => [
                    'en' => 'Courses related to programming and software development',
                    'es' => 'Cursos relacionados con programación y desarrollo de software',
                    'fr' => 'Cours liés à la programmation et au développement de logiciels',
                ],
                'slug' => 'programming',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => [
                    'en' => 'Design',
                    'es' => 'Diseño',
                    'fr' => 'Design',
                ],
                'description' => [
                    'en' => 'Courses related to graphic design, UI/UX, and visual arts',
                    'es' => 'Cursos relacionados con diseño gráfico, UI/UX y artes visuales',
                    'fr' => 'Cours liés au design graphique, UI/UX et aux arts visuels',
                ],
                'slug' => 'design',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => [
                    'en' => 'Business',
                    'es' => 'Negocios',
                    'fr' => 'Affaires',
                ],
                'description' => [
                    'en' => 'Courses related to business, entrepreneurship, and management',
                    'es' => 'Cursos relacionados con negocios, emprendimiento y gestión',
                    'fr' => 'Cours liés aux affaires, à l\'entrepreneuriat et à la gestion',
                ],
                'slug' => 'business',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => [
                    'en' => 'Marketing',
                    'es' => 'Marketing',
                    'fr' => 'Marketing',
                ],
                'description' => [
                    'en' => 'Courses related to marketing, advertising, and sales',
                    'es' => 'Cursos relacionados con marketing, publicidad y ventas',
                    'fr' => 'Cours liés au marketing, à la publicité et aux ventes',
                ],
                'slug' => 'marketing',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => [
                    'en' => 'Personal Development',
                    'es' => 'Desarrollo Personal',
                    'fr' => 'Développement Personnel',
                ],
                'description' => [
                    'en' => 'Courses related to personal growth, productivity, and self-improvement',
                    'es' => 'Cursos relacionados con crecimiento personal, productividad y superación',
                    'fr' => 'Cours liés à la croissance personnelle, à la productivité et à l\'amélioration de soi',
                ],
                'slug' => 'personal-development',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            CourseCategory::create($category);
        }
    }
}
