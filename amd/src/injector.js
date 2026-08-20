// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Injects the course total grade badge into block_myoverview cards.
 *
 * @module local_coursegradebadge/injector
 * @copyright 2026 FES Iztacala, UNAM — Psicología SUAyED
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/templates'], function(Ajax, Str, Templates) {

    const SELECTORS = {
        coursesView: '[data-region="courses-view"]',
        card: '[data-region="course-info-container"], .course-card',
        progress: '.progress, [data-region="progress"]',
        badge: '.lcgb-badge',
    };

    const DEBOUNCE_MS = 150;
    const BATCH_LIMIT = 50;

    let debounceTimer = null;
    let pendingCourses = new Set();
    let strings = null;
    let observer = null;

    function extractCourseId(card) {
        const explicit = card.closest('[data-course-id]');
        if (explicit) {
            return parseInt(explicit.dataset.courseId, 10);
        }
        const match = card.id.match(/course-info-container-(\d+)/);
        return match ? parseInt(match[1], 10) : NaN;
    }

    function findAnchor(card) {
        return card.querySelector(SELECTORS.progress);
    }

    function renderBadge(context) {
        return Templates.render('local_coursegradebadge/grade_badge', context);
    }

    function injectBadges(gradesByCourse) {
        document.querySelectorAll(SELECTORS.coursesView + ' ' + SELECTORS.card).forEach(function(card) {
            const courseid = extractCourseId(card);
            if (isNaN(courseid) || !(courseid in gradesByCourse)) {
                return;
            }
            const grade = gradesByCourse[courseid];
            if (grade.reason !== 'ok') {
                return;
            }
            const container = findAnchor(card) || card;
            const existing = container.parentNode.querySelector(SELECTORS.badge + '[data-lcgb-course="' + courseid + '"]');
            if (existing) {
                return;
            }
            const context = {
                courseid: courseid,
                formatted: grade.formatted,
                label: strings.label,
                arialabel: strings.arialabel.replace('{$a}', grade.formatted),
            };
            renderBadge(context).then(function(html) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const node = wrapper.firstChild;
                node.dataset.lcgbCourse = courseid;
                const anchor = findAnchor(card);
                if (anchor && anchor.nextSibling) {
                    anchor.parentNode.insertBefore(node, anchor.nextSibling);
                } else if (anchor) {
                    anchor.parentNode.appendChild(node);
                } else {
                    card.appendChild(node);
                }
                return null;
            }).catch(function() {
                return null;
            });
        });
    }

    function fetchGrades() {
        if (pendingCourses.size === 0) {
            return;
        }
        const courseids = Array.from(pendingCourses).slice(0, BATCH_LIMIT);
        pendingCourses = new Set(Array.from(pendingCourses).slice(BATCH_LIMIT));
        const request = {
            methodname: 'local_coursegradebadge_get_grades',
            args: {courseids: courseids},
        };
        Ajax.call([request])[0].then(function(response) {
            const gradesByCourse = {};
            response.grades.forEach(function(grade) {
                gradesByCourse[grade.courseid] = grade;
            });
            injectBadges(gradesByCourse);
            if (pendingCourses.size > 0) {
                fetchGrades();
            }
            return null;
        }).catch(function() {
            return null;
        });
    }

    function scheduleFetch() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(fetchGrades, DEBOUNCE_MS);
    }

    function collectPendingCards() {
        document.querySelectorAll(SELECTORS.coursesView + ' ' + SELECTORS.card).forEach(function(card) {
            const courseid = extractCourseId(card);
            if (!isNaN(courseid) && !card.querySelector(SELECTORS.badge)) {
                pendingCourses.add(courseid);
            }
        });
        if (pendingCourses.size > 0) {
            scheduleFetch();
        }
    }

    function initObserver() {
        if (observer) {
            return;
        }
        const target = document.querySelector(SELECTORS.coursesView);
        if (!target) {
            return;
        }
        observer = new MutationObserver(function(mutations) {
            let relevant = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    relevant = true;
                }
            });
            if (relevant) {
                collectPendingCards();
            }
        });
        observer.observe(target, {childList: true, subtree: true});
    }

    function init() {
        Str.get_strings([
            {key: 'badge:coursegrade', component: 'local_coursegradebadge'},
            {key: 'badge:arialabel', component: 'local_coursegradebadge'},
        ]).then(function(results) {
            strings = {label: results[0], arialabel: results[1]};
            collectPendingCards();
            initObserver();
            return null;
        }).catch(function() {
            return null;
        });
    }

    return {init: init};
});
