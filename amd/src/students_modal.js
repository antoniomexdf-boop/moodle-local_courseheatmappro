/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2026 Jesus Antonio Jimenez Aviña <support@kaviratech.com> <moodle@kaviratech.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Open student list modals for Course Engagement Map Pro.
 *
 * @module local_courseheatmappro/students_modal
 */
define(
    ['core/ajax', 'core/modal_factory', 'core/notification', 'core/templates'],
    function(Ajax, ModalFactory, Notification, Templates) {
    var createModal = function() {
        return ModalFactory.create({
            title: '',
            body: '',
            large: true
        });
    };

    var setLoading = function(modal) {
        return Templates.renderForPromise('core/loading', {}).then(function(rendered) {
            modal.setBody(rendered.html);
            Templates.runTemplateJS(rendered.js);
            return modal;
        });
    };

    var renderModal = function(modal, payload) {
        return Templates.renderForPromise('local_courseheatmappro/student_list_modal', payload).then(function(rendered) {
            modal.setTitle(payload.title || '');
            modal.setBody(rendered.html);
            Templates.runTemplateJS(rendered.js);
            return null;
        });
    };

    var loadStudents = function(modal, courseid, cmid, listtype) {
        return Ajax.call([{
            methodname: 'local_courseheatmappro_get_module_students',
            args: {
                courseid: courseid,
                cmid: cmid,
                listtype: listtype
            }
        }])[0].then(function(payload) {
            return renderModal(modal, payload);
        }).catch(function(ex) {
            modal.hide();
            Notification.exception(ex);
        });
    };

    var openStudents = function(button) {
        var courseid = parseInt(button.getAttribute('data-courseid'), 10) || 0;
        var cmid = parseInt(button.getAttribute('data-cmid'), 10) || 0;
        var listtype = button.getAttribute('data-listtype') || '';

        if (!courseid || !cmid || !listtype) {
            return;
        }

        createModal().then(function(modal) {
            return setLoading(modal).then(function() {
                modal.show();
                return loadStudents(modal, courseid, cmid, listtype);
            });
        }).catch(Notification.exception);
    };

    return {
        init: function() {
            document.querySelectorAll('.js-lchp-open-students').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    openStudents(button);
                });
            });
        }
    };
});
