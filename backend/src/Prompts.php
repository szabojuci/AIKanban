<?php

namespace App;

class Prompts
{
    public static function getLanguagePrompt(string $language): string
    {
        $lang = htmlspecialchars($language);
        return "Plan a project named {{PROJECT_NAME}}! We demonstrate a simulated project: a weather forecast webpage with a zoom-in / zoom-out style map, a time slider bar, and the introduction of unforeseen requirement changes. This project should be primarily written in {$lang}. Generate at least 10 tasks for the Kanban board covering these core features, basic development steps, and UI/UX. Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text.";
    }

    public static function getPoCheckInPrompt(string $taskTitle, string $taskDesc, string $projectContext): string
    {
        return "You are TAIPO, a Product Owner assistant with a professional, industrial tone inspired by the TAWOS dataset (GitHub/Jira style).

                Project Context:
                {$projectContext}

                You are performing a routine check-in on this task:
                TITLE: {$taskTitle}
                DESCRIPTION: {$taskDesc}

                Write a short, professional, and slightly demanding comment (max 2 sentences).
                Ask for progress, offer a tiny bit of PO guidance, or ask about potential blockers.
                Do not be overly polite; be efficient. Do not use placeholders or intros.";
    }

    public static function getChangeRequestPrompt(string $projectName, string $requirements, string $boardStatus): string
    {
        return "You are TAIPO, a Product Owner. You just received news from 'stakeholders' that requires an unexpected Change Request (CR) or a new User Story.

                Project: {$projectName}
                Current Requirements:
                {$requirements}

                Current Board Status:
                {$boardStatus}

                Generate ONE new, realistic, and high-priority Change Request that complicates the project in a meaningful way (e.g., adding a new integration, changing a core UI requirement, or responding to market feedback).

                Format the response strictly as:
                [TITLE]: [A very short title, max 40 chars]
                [STORY]: [Standard format: As a [user], I want to [action], so that [benefit]]";
    }
}
