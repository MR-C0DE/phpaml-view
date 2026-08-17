<?php

declare(strict_types=1);

namespace App\Support;

final class Copy
{
    private static string $locale = 'en';

    private const TEXT = [
        'brand' => ['en' => 'AML Tasks', 'fr' => 'Tâches AML'],
        'dashboard' => ['en' => 'Dashboard', 'fr' => 'Tableau de bord'],
        'tasks' => ['en' => 'Tasks', 'fr' => 'Tâches'],
        'settings' => ['en' => 'Settings', 'fr' => 'Paramètres'],
        'footer' => ['en' => 'Built with PHPAML · AML View', 'fr' => 'Créé avec PHPAML · AML View'],
        'hero.eyebrow' => ['en' => 'AML VIEW DEMO', 'fr' => 'DÉMO AML VIEW'],
        'hero.title' => ['en' => 'Your work, clearly organized.', 'fr' => 'Votre travail, clairement organisé.'],
        'hero.copy' => ['en' => 'A complete declarative frontend written in PHP, powered in the browser by PHPAML Engine.', 'fr' => 'Une interface déclarative complète écrite en PHP et exécutée dans le navigateur par PHPAML Engine.'],
        'hero.open' => ['en' => 'Open tasks', 'fr' => 'Ouvrir les tâches'],
        'hero.preferences' => ['en' => 'View settings', 'fr' => 'Voir les paramètres'],
        'hero.ready' => ['en' => 'tasks ready', 'fr' => 'tâches prêtes'],
        'hero.declarative' => ['en' => '100% declarative', 'fr' => '100 % déclaratif'],
        'glance' => ['en' => 'Today at a glance', 'fr' => 'Aujourd’hui en un coup d’œil'],
        'total' => ['en' => 'Total tasks', 'fr' => 'Total des tâches'],
        'completed' => ['en' => 'Completed', 'fr' => 'Terminées'],
        'progress' => ['en' => 'In progress', 'fr' => 'En cours'],
        'workspace' => ['en' => 'WORKSPACE', 'fr' => 'ESPACE DE TRAVAIL'],
        'tasks.copy' => ['en' => 'State, collections and interactions remain entirely in the browser.', 'fr' => 'Les états, collections et interactions restent entièrement dans le navigateur.'],
        'tasks.total' => ['en' => 'total', 'fr' => 'au total'],
        'tasks.required' => ['en' => 'Give the task a name', 'fr' => 'Donnez un nom à la tâche'],
        'tasks.add' => ['en' => 'Add task', 'fr' => 'Ajouter la tâche'],
        'tasks.all' => ['en' => 'All', 'fr' => 'Toutes'],
        'tasks.open' => ['en' => 'Open', 'fr' => 'Ouvertes'],
        'tasks.saved' => ['en' => 'Task saved locally.', 'fr' => 'Tâche enregistrée localement.'],
        'task.label' => ['en' => 'Task', 'fr' => 'Tâche'],
        'preferences' => ['en' => 'PREFERENCES', 'fr' => 'PRÉFÉRENCES'],
        'appearance' => ['en' => 'Appearance', 'fr' => 'Apparence'],
        'appearance.copy' => ['en' => 'Choose how AML Tasks looks on this device.', 'fr' => 'Choisissez l’apparence de Tâches AML sur cet appareil.'],
        'language' => ['en' => 'Language', 'fr' => 'Langue'],
        'language.copy' => ['en' => 'Choose the interface language.', 'fr' => 'Choisissez la langue de l’interface.'],
        'dynamic' => ['en' => 'DYNAMIC ROUTE', 'fr' => 'ROUTE DYNAMIQUE'],
        'task.details' => ['en' => 'Task', 'fr' => 'Tâche'],
        'task.route' => ['en' => 'This page comes from views/pages/tasks/[id]/page.php.', 'fr' => 'Cette page provient de views/pages/tasks/[id]/page.php.'],
        'task.back' => ['en' => 'Back to tasks', 'fr' => 'Retour aux tâches'],
        'not-found' => ['en' => 'Page not found', 'fr' => 'Page introuvable'],
        'return-home' => ['en' => 'Return home', 'fr' => 'Retour à l’accueil'],
        'loading' => ['en' => 'Loading workspace…', 'fr' => 'Chargement de l’espace de travail…'],
        'error' => ['en' => 'AML Tasks could not load this page.', 'fr' => 'Tâches AML n’a pas pu charger cette page.'],
    ];

    public static function locale(string $locale): void
    {
        self::$locale = in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
    }

    public static function current(): string { return self::$locale; }

    public static function text(string $key): string
    {
        return self::TEXT[$key][self::$locale] ?? self::TEXT[$key]['en'] ?? $key;
    }
}
