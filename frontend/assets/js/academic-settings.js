/**
 * Academic Settings Module
 * EduManage Pro - School Management System
 *
 * Handles all Academic Settings functionality:
 * - Classes management (CRUD)
 * - Sections management (dependent on classes)
 * - Subjects management (CRUD)
 * - Class-Subject assignments
 *
 * @version 2.0.0
 * @author EduManage Pro
 */

const AcademicSettings = (function() {
    'use strict';

    // =========================================================================
    // CONFIGURATION
    // =========================================================================
    // Detect correct API path based on current page location
    const currentPath = window.location.pathname;
    const isInPagesFolder = currentPath.includes('/pages/');
    const API_BASE_PATH = isInPagesFolder ? '../../backend/api' : '../backend/api';

    const CONFIG = {
        apiBase: API_BASE_PATH + '/academic.php',
        cacheExpiry: 5 * 60 * 1000, // 5 minutes
        debounceDelay: 300,
        toastDuration: 3000
    };

    // =========================================================================
    // STATE MANAGEMENT
    // =========================================================================
    let state = {
        classes: [],
        sections: {},      // Keyed by class_id
        subjects: [],
        classSubjects: {}, // Keyed by class_id
        loading: {
            classes: false,
            sections: false,
            subjects: false
        },
        cache: {
            classes: { data: null, timestamp: 0 },
            sections: {},
            subjects: { data: null, timestamp: 0 }
        },
        currentTab: 'classes',
        editMode: {
            type: null,    // 'class', 'section', 'subject'
            id: null
        }
    };

    // =========================================================================
    // UTILITY FUNCTIONS
    // =========================================================================

    /**
     * Debounce function for search inputs
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Generate UUID v4
     */
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} animate-slideIn`;

        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };

        toast.innerHTML = `
            <div class="toast-content">
                ${icons[type] || icons.info}
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('animate-slideOut');
            setTimeout(() => toast.remove(), 300);
        }, CONFIG.toastDuration);
    }

    /**
     * Create toast container if not exists
     */
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    }

    /**
     * Show skeleton loader
     */
    function showSkeleton(containerId, count = 5) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let skeletonHTML = '';
        for (let i = 0; i < count; i++) {
            skeletonHTML += `
                <tr class="skeleton-row">
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-badge"></div></td>
                    <td><div class="skeleton skeleton-actions"></div></td>
                </tr>
            `;
        }
        container.innerHTML = skeletonHTML;
    }

    /**
     * Check if cache is valid
     */
    function isCacheValid(cacheEntry) {
        return cacheEntry && cacheEntry.data &&
               (Date.now() - cacheEntry.timestamp) < CONFIG.cacheExpiry;
    }

    // =========================================================================
    // API FUNCTIONS
    // =========================================================================

    /**
     * Make API request with graceful error handling
     */
    async function apiRequest(action, method = 'GET', data = null, silent = false) {
        const url = method === 'GET' && data
            ? `${CONFIG.apiBase}?action=${action}&${new URLSearchParams(data)}`
            : `${CONFIG.apiBase}?action=${action}`;

        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        if (method !== 'GET' && data) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);

            // Handle non-JSON responses
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                if (!silent) console.warn('API returned non-JSON response for:', action);
                return { success: false, data: [], error: 'Invalid response format' };
            }

            const result = await response.json();

            if (!result.success) {
                if (!silent) console.warn('API returned error for:', action, result.error);
                return { success: false, data: [], error: result.error || 'Request failed' };
            }

            return result;
        } catch (error) {
            if (!silent) console.warn('API request failed for:', action, error.message);
            return { success: false, data: [], error: error.message };
        }
    }

    // =========================================================================
    // CLASSES MANAGEMENT
    // =========================================================================

    /**
     * Load all classes
     */
    async function loadClasses(forceRefresh = false, silent = true) {
        if (!forceRefresh && isCacheValid(state.cache.classes)) {
            state.classes = Array.isArray(state.cache.classes.data) ? state.cache.classes.data : [];
            renderClassesTable();
            return state.classes;
        }

        state.loading.classes = true;
        showSkeleton('classesTableBody');

        const result = await apiRequest('classes', 'GET', null, silent);
        // API returns { classes: [...], total: N }, extract the array safely
        let classesData = result?.data;
        if (Array.isArray(classesData)) {
            state.classes = classesData;
        } else if (classesData && Array.isArray(classesData.classes)) {
            state.classes = classesData.classes;
        } else {
            state.classes = [];
        }

        // Update cache
        state.cache.classes = {
            data: state.classes,
            timestamp: Date.now()
        };

        renderClassesTable();
        state.loading.classes = false;
        return state.classes;
    }

    /**
     * Render classes table
     */
    function renderClassesTable() {
        const tbody = document.getElementById('classesTableBody');
        if (!tbody) return;

        // Ensure state.classes is always an array
        if (!Array.isArray(state.classes)) {
            state.classes = [];
        }

        if (state.classes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No classes found. Click "Add Class" to create one.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.classes.map(cls => `
            <tr data-class-id="${cls.id}">
                <td>
                    <div class="class-info">
                        <span class="class-name">${escapeHtml(cls.class_name)}</span>
                        <small class="text-muted d-block">Order: ${cls.numeric_value}</small>
                    </div>
                </td>
                <td>
                    <div class="sections-badges">
                        ${renderSectionBadges(cls)}
                    </div>
                </td>
                <td>
                    <span class="badge badge-info">${cls.section_count || 0} Sections</span>
                    <span class="badge badge-secondary ml-1">${cls.total_students || 0} Students</span>
                </td>
                <td>
                    <span class="status-badge status-${cls.status.toLowerCase()}">
                        ${cls.status}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-icon btn-outline-primary"
                                onclick="AcademicSettings.editClass(${cls.id})"
                                title="Edit Class">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-success"
                                onclick="AcademicSettings.showAddSectionModal(${cls.id}, '${escapeHtml(cls.class_name)}')"
                                title="Add Section">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-info"
                                onclick="AcademicSettings.manageClassSubjects(${cls.id})"
                                title="Manage Subjects">
                            <i class="fas fa-book"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger"
                                onclick="AcademicSettings.deleteClass(${cls.id}, '${escapeHtml(cls.class_name)}')"
                                title="Delete Class">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Render section badges for a class
     */
    function renderSectionBadges(cls) {
        if (!cls.sections || cls.sections.length === 0) {
            return '<span class="text-muted">No sections</span>';
        }

        return cls.sections.map(sec => `
            <span class="section-badge"
                  onclick="AcademicSettings.editSection(${sec.id})"
                  title="Click to edit">
                ${escapeHtml(sec.section_name)}
                <small>(${sec.student_count || 0})</small>
            </span>
        `).join('');
    }

    /**
     * Add new class
     */
    async function addClass(classData) {
        try {
            const result = await apiRequest('add_class', 'POST', classData);
            showToast('Class added successfully!', 'success');

            // Invalidate cache and reload
            state.cache.classes.timestamp = 0;
            await loadClasses(true);

            closeModal('classModal');
            return result;
        } catch (error) {
            showToast('Failed to add class: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Update class
     */
    async function updateClass(id, classData) {
        try {
            const result = await apiRequest('update_class', 'PUT', { id, ...classData });
            showToast('Class updated successfully!', 'success');

            // Invalidate cache and reload
            state.cache.classes.timestamp = 0;
            await loadClasses(true);

            closeModal('classModal');
            return result;
        } catch (error) {
            showToast('Failed to update class: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Delete class
     */
    async function deleteClass(id, className) {
        const confirmed = await showConfirmDialog(
            'Delete Class',
            `Are you sure you want to delete "${className}"? This will also delete all sections in this class.`,
            'danger'
        );

        if (!confirmed) return;

        try {
            await apiRequest('delete_class', 'DELETE', { id });
            showToast('Class deleted successfully!', 'success');

            // Invalidate cache and reload
            state.cache.classes.timestamp = 0;
            await loadClasses(true);
        } catch (error) {
            showToast('Failed to delete class: ' + error.message, 'error');
        }
    }

    /**
     * Show class modal for add/edit
     */
    function showClassModal(classId = null) {
        state.editMode = { type: 'class', id: classId };

        const modal = document.getElementById('classModal');
        const title = modal.querySelector('.modal-title');
        const form = document.getElementById('classForm');

        if (classId) {
            title.textContent = 'Edit Class';
            const cls = state.classes.find(c => c.id === classId);
            if (cls) {
                form.elements['class_name'].value = cls.class_name;
                form.elements['numeric_value'].value = cls.numeric_value;
                form.elements['status'].value = cls.status;
                form.elements['initial_sections'].value = '';
                form.elements['initial_sections'].disabled = true;
            }
        } else {
            title.textContent = 'Add New Class';
            form.reset();
            form.elements['initial_sections'].disabled = false;
        }

        openModal('classModal');
    }

    /**
     * Edit class
     */
    function editClass(classId) {
        showClassModal(classId);
    }

    // =========================================================================
    // SECTIONS MANAGEMENT
    // =========================================================================

    /**
     * Load sections for a specific class
     */
    async function loadSectionsForClass(classId, forceRefresh = false, silent = true) {
        if (!classId) return [];

        const cacheKey = `sections_${classId}`;
        if (!forceRefresh && state.cache.sections[cacheKey] &&
            isCacheValid(state.cache.sections[cacheKey])) {
            state.sections[classId] = Array.isArray(state.cache.sections[cacheKey].data) ? state.cache.sections[cacheKey].data : [];
            return state.sections[classId];
        }

        state.loading.sections = true;

        const result = await apiRequest('sections', 'GET', { class_id: classId }, silent);
        // API returns { sections: [...] }, extract the array safely
        let sectionsData = result?.data;
        if (Array.isArray(sectionsData)) {
            state.sections[classId] = sectionsData;
        } else if (sectionsData && Array.isArray(sectionsData.sections)) {
            state.sections[classId] = sectionsData.sections;
        } else {
            state.sections[classId] = [];
        }

        // Update cache
        state.cache.sections[cacheKey] = {
            data: state.sections[classId],
            timestamp: Date.now()
        };

        state.loading.sections = false;
        return state.sections[classId];
    }

    /**
     * Add section to a class
     */
    async function addSection(sectionData) {
        try {
            const result = await apiRequest('add_section', 'POST', sectionData);
            showToast('Section added successfully!', 'success');

            // Invalidate caches
            state.cache.classes.timestamp = 0;
            delete state.cache.sections[`sections_${sectionData.class_id}`];

            await loadClasses(true);
            closeModal('sectionModal');
            return result;
        } catch (error) {
            showToast('Failed to add section: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Update section
     */
    async function updateSection(id, sectionData) {
        try {
            const result = await apiRequest('update_section', 'PUT', { id, ...sectionData });
            showToast('Section updated successfully!', 'success');

            // Invalidate caches
            state.cache.classes.timestamp = 0;
            Object.keys(state.cache.sections).forEach(key => {
                state.cache.sections[key].timestamp = 0;
            });

            await loadClasses(true);
            closeModal('sectionModal');
            return result;
        } catch (error) {
            showToast('Failed to update section: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Delete section
     */
    async function deleteSection(id, sectionName, classId) {
        const confirmed = await showConfirmDialog(
            'Delete Section',
            `Are you sure you want to delete section "${sectionName}"?`,
            'danger'
        );

        if (!confirmed) return;

        try {
            await apiRequest('delete_section', 'DELETE', { id });
            showToast('Section deleted successfully!', 'success');

            // Invalidate caches
            state.cache.classes.timestamp = 0;
            delete state.cache.sections[`sections_${classId}`];

            await loadClasses(true);
        } catch (error) {
            showToast('Failed to delete section: ' + error.message, 'error');
        }
    }

    /**
     * Show add section modal
     */
    function showAddSectionModal(classId, className) {
        state.editMode = { type: 'section', id: null, classId };

        const modal = document.getElementById('sectionModal');
        const title = modal.querySelector('.modal-title');
        const form = document.getElementById('sectionForm');

        title.textContent = `Add Section to ${className}`;
        form.reset();
        form.elements['class_id'].value = classId;

        openModal('sectionModal');
    }

    /**
     * Edit section
     */
    async function editSection(sectionId) {
        // Find section in loaded classes
        let section = null;
        let classId = null;

        for (const cls of state.classes) {
            if (cls.sections) {
                section = cls.sections.find(s => s.id === sectionId);
                if (section) {
                    classId = cls.id;
                    break;
                }
            }
        }

        if (!section) {
            showToast('Section not found', 'error');
            return;
        }

        state.editMode = { type: 'section', id: sectionId, classId };

        const modal = document.getElementById('sectionModal');
        const title = modal.querySelector('.modal-title');
        const form = document.getElementById('sectionForm');

        title.textContent = 'Edit Section';
        form.elements['class_id'].value = classId;
        form.elements['section_name'].value = section.section_name;
        form.elements['capacity'].value = section.capacity || 40;
        form.elements['room_number'].value = section.room_number || '';
        form.elements['status'].value = section.status || 'Active';

        openModal('sectionModal');
    }

    // =========================================================================
    // SUBJECTS MANAGEMENT
    // =========================================================================

    /**
     * Load all subjects
     */
    async function loadSubjects(forceRefresh = false, silent = true) {
        if (!forceRefresh && isCacheValid(state.cache.subjects)) {
            state.subjects = Array.isArray(state.cache.subjects.data) ? state.cache.subjects.data : [];
            renderSubjectsTable();
            return state.subjects;
        }

        state.loading.subjects = true;
        showSkeleton('subjectsTableBody');

        const result = await apiRequest('subjects', 'GET', null, silent);
        // API returns { subjects: [...], total: N }, extract the array safely
        let subjectsData = result?.data;
        if (Array.isArray(subjectsData)) {
            state.subjects = subjectsData;
        } else if (subjectsData && Array.isArray(subjectsData.subjects)) {
            state.subjects = subjectsData.subjects;
        } else {
            state.subjects = [];
        }

        // Update cache
        state.cache.subjects = {
            data: state.subjects,
            timestamp: Date.now()
        };

        renderSubjectsTable();
        state.loading.subjects = false;
        return state.subjects;
    }

    /**
     * Load subjects for a specific class
     */
    async function loadSubjectsForClass(classId, silent = true) {
        if (!classId) return [];

        const result = await apiRequest('subjects', 'GET', { class_id: classId }, silent);
        // API returns { subjects: [...], total: N }, extract the array safely
        let subjectsData = result?.data;
        if (Array.isArray(subjectsData)) {
            state.classSubjects[classId] = subjectsData;
        } else if (subjectsData && Array.isArray(subjectsData.subjects)) {
            state.classSubjects[classId] = subjectsData.subjects;
        } else {
            state.classSubjects[classId] = [];
        }
        return state.classSubjects[classId];
    }

    /**
     * Render subjects table
     */
    function renderSubjectsTable() {
        const tbody = document.getElementById('subjectsTableBody');
        if (!tbody) return;

        // Ensure state.subjects is always an array
        if (!Array.isArray(state.subjects)) {
            state.subjects = [];
        }

        if (state.subjects.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No subjects found. Click "Add Subject" to create one.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.subjects.map(sub => `
            <tr data-subject-id="${sub.id}">
                <td>
                    <div class="subject-info">
                        <span class="subject-code" style="color: ${sub.color_code || '#6366F1'}">
                            ${escapeHtml(sub.subject_code)}
                        </span>
                    </div>
                </td>
                <td>
                    <div class="subject-name-cell">
                        <span class="subject-name">${escapeHtml(sub.subject_name)}</span>
                        ${sub.short_name ? `<small class="text-muted d-block">${escapeHtml(sub.short_name)}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge badge-${getSubjectTypeBadgeClass(sub.subject_type)}">
                        ${sub.subject_type}
                    </span>
                </td>
                <td>
                    <span class="text-muted">${sub.max_marks}/${sub.pass_marks}</span>
                </td>
                <td>
                    ${sub.assigned_classes
                        ? `<span class="text-truncate" style="max-width: 200px; display: inline-block;" title="${escapeHtml(sub.assigned_classes)}">${escapeHtml(sub.assigned_classes)}</span>`
                        : '<span class="text-muted">Not assigned</span>'}
                </td>
                <td>
                    <span class="status-badge status-${sub.status.toLowerCase()}">
                        ${sub.status}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-icon btn-outline-primary"
                                onclick="AcademicSettings.editSubject(${sub.id})"
                                title="Edit Subject">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger"
                                onclick="AcademicSettings.deleteSubject(${sub.id}, '${escapeHtml(sub.subject_name)}')"
                                title="Delete Subject">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Get badge class for subject type
     */
    function getSubjectTypeBadgeClass(type) {
        const classes = {
            'Core': 'primary',
            'Theory': 'info',
            'Practical': 'success',
            'Lab': 'warning',
            'Elective': 'secondary',
            'Activity': 'purple'
        };
        return classes[type] || 'secondary';
    }

    /**
     * Add subject
     */
    async function addSubject(subjectData) {
        try {
            const result = await apiRequest('add_subject', 'POST', subjectData);
            showToast('Subject added successfully!', 'success');

            // Invalidate cache and reload
            state.cache.subjects.timestamp = 0;
            await loadSubjects(true);

            closeModal('subjectModal');
            return result;
        } catch (error) {
            showToast('Failed to add subject: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Update subject
     */
    async function updateSubject(id, subjectData) {
        try {
            const result = await apiRequest('update_subject', 'PUT', { id, ...subjectData });
            showToast('Subject updated successfully!', 'success');

            // Invalidate cache and reload
            state.cache.subjects.timestamp = 0;
            await loadSubjects(true);

            closeModal('subjectModal');
            return result;
        } catch (error) {
            showToast('Failed to update subject: ' + error.message, 'error');
            throw error;
        }
    }

    /**
     * Delete subject
     */
    async function deleteSubject(id, subjectName) {
        const confirmed = await showConfirmDialog(
            'Delete Subject',
            `Are you sure you want to delete "${subjectName}"? This will also remove all class assignments.`,
            'danger'
        );

        if (!confirmed) return;

        try {
            await apiRequest('delete_subject', 'DELETE', { id });
            showToast('Subject deleted successfully!', 'success');

            // Invalidate cache and reload
            state.cache.subjects.timestamp = 0;
            await loadSubjects(true);
        } catch (error) {
            showToast('Failed to delete subject: ' + error.message, 'error');
        }
    }

    /**
     * Show subject modal
     */
    function showSubjectModal(subjectId = null) {
        state.editMode = { type: 'subject', id: subjectId };

        const modal = document.getElementById('subjectModal');
        const title = modal.querySelector('.modal-title');
        const form = document.getElementById('subjectForm');

        if (subjectId) {
            title.textContent = 'Edit Subject';
            const sub = state.subjects.find(s => s.id === subjectId);
            if (sub) {
                form.elements['subject_code'].value = sub.subject_code;
                form.elements['subject_name'].value = sub.subject_name;
                form.elements['short_name'].value = sub.short_name || '';
                form.elements['subject_type'].value = sub.subject_type;
                form.elements['max_marks'].value = sub.max_marks;
                form.elements['pass_marks'].value = sub.pass_marks;
                form.elements['color_code'].value = sub.color_code || '#6366F1';
                form.elements['status'].value = sub.status;
            }
        } else {
            title.textContent = 'Add New Subject';
            form.reset();
            form.elements['color_code'].value = '#6366F1';
        }

        openModal('subjectModal');
    }

    /**
     * Edit subject
     */
    function editSubject(subjectId) {
        showSubjectModal(subjectId);
    }

    // =========================================================================
    // CLASS-SUBJECT ASSIGNMENTS
    // =========================================================================

    /**
     * Manage class subjects
     */
    async function manageClassSubjects(classId) {
        const cls = state.classes.find(c => c.id === classId);
        if (!cls) return;

        // Load all subjects if not loaded
        if (state.subjects.length === 0) {
            await loadSubjects();
        }

        // Load assigned subjects for this class
        const assignedSubjects = await loadSubjectsForClass(classId);
        const assignedIds = assignedSubjects.map(s => s.id);

        const modal = document.getElementById('classSubjectsModal');
        const title = modal.querySelector('.modal-title');
        const container = document.getElementById('subjectsCheckboxContainer');

        title.textContent = `Assign Subjects to ${cls.class_name}`;

        container.innerHTML = state.subjects.map(sub => `
            <div class="subject-checkbox-item">
                <label class="checkbox-label">
                    <input type="checkbox"
                           name="subjects"
                           value="${sub.id}"
                           ${assignedIds.includes(sub.id) ? 'checked' : ''}>
                    <span class="checkbox-custom"></span>
                    <span class="subject-label">
                        <span class="subject-code" style="color: ${sub.color_code || '#6366F1'}">
                            ${escapeHtml(sub.subject_code)}
                        </span>
                        <span class="subject-name">${escapeHtml(sub.subject_name)}</span>
                    </span>
                </label>
            </div>
        `).join('');

        document.getElementById('classSubjectsForm').dataset.classId = classId;
        openModal('classSubjectsModal');
    }

    /**
     * Save class-subject assignments
     */
    async function saveClassSubjects(classId, subjectIds) {
        try {
            await apiRequest('assign_subjects', 'POST', {
                class_id: classId,
                subject_ids: subjectIds
            });
            showToast('Subjects assigned successfully!', 'success');

            // Invalidate cache
            state.cache.subjects.timestamp = 0;
            await loadSubjects(true);

            closeModal('classSubjectsModal');
        } catch (error) {
            showToast('Failed to assign subjects: ' + error.message, 'error');
        }
    }

    // =========================================================================
    // DEPENDENT DROPDOWNS (For other modules)
    // =========================================================================

    /**
     * Populate class dropdown
     */
    async function populateClassDropdown(selectElement, options = {}) {
        const { includeEmpty = true, emptyText = 'Select Class' } = options;

        if (!selectElement) return;

        // Load classes if not cached
        if (state.classes.length === 0) {
            await loadClasses();
        }

        let html = includeEmpty ? `<option value="">${emptyText}</option>` : '';

        state.classes
            .filter(c => c.status === 'Active')
            .sort((a, b) => a.numeric_value - b.numeric_value)
            .forEach(cls => {
                html += `<option value="${cls.id}" data-name="${escapeHtml(cls.class_name)}">${escapeHtml(cls.class_name)}</option>`;
            });

        selectElement.innerHTML = html;
    }

    /**
     * Populate section dropdown based on selected class
     */
    async function populateSectionDropdown(selectElement, classId, options = {}) {
        const { includeEmpty = true, emptyText = 'Select Section' } = options;

        if (!selectElement) return;

        if (!classId) {
            selectElement.innerHTML = includeEmpty ? `<option value="">${emptyText}</option>` : '';
            selectElement.disabled = true;
            return;
        }

        selectElement.disabled = false;
        selectElement.innerHTML = '<option value="">Loading...</option>';

        const sections = await loadSectionsForClass(classId);

        let html = includeEmpty ? `<option value="">${emptyText}</option>` : '';

        sections
            .filter(s => s.status === 'Active')
            .forEach(sec => {
                html += `<option value="${sec.id}" data-name="${escapeHtml(sec.section_name)}">${escapeHtml(sec.section_name)}</option>`;
            });

        selectElement.innerHTML = html;
    }

    /**
     * Populate subject dropdown based on selected class
     */
    async function populateSubjectDropdown(selectElement, classId, options = {}) {
        const { includeEmpty = true, emptyText = 'Select Subject', includeAll = false } = options;

        if (!selectElement) return;

        let subjects;

        if (classId) {
            subjects = await loadSubjectsForClass(classId);
        } else if (includeAll) {
            if (state.subjects.length === 0) {
                await loadSubjects();
            }
            subjects = state.subjects;
        } else {
            selectElement.innerHTML = includeEmpty ? `<option value="">${emptyText}</option>` : '';
            return;
        }

        let html = includeEmpty ? `<option value="">${emptyText}</option>` : '';

        subjects
            .filter(s => s.status === 'Active')
            .forEach(sub => {
                html += `<option value="${sub.id}" data-code="${escapeHtml(sub.subject_code)}">${escapeHtml(sub.subject_name)}</option>`;
            });

        selectElement.innerHTML = html;
    }

    /**
     * Setup dependent dropdown chain (Class -> Section)
     */
    function setupClassSectionChain(classSelect, sectionSelect, options = {}) {
        const { onChange = null } = options;

        classSelect.addEventListener('change', async function() {
            const classId = this.value;
            await populateSectionDropdown(sectionSelect, classId);

            if (onChange) {
                onChange({ classId, sectionId: null });
            }
        });

        sectionSelect.addEventListener('change', function() {
            if (onChange) {
                onChange({
                    classId: classSelect.value,
                    sectionId: this.value
                });
            }
        });
    }

    /**
     * Setup dependent dropdown chain (Class -> Section -> Subject)
     */
    function setupFullChain(classSelect, sectionSelect, subjectSelect, options = {}) {
        const { onChange = null } = options;

        setupClassSectionChain(classSelect, sectionSelect, {
            onChange: async (data) => {
                if (subjectSelect) {
                    await populateSubjectDropdown(subjectSelect, data.classId);
                }
                if (onChange) {
                    onChange({ ...data, subjectId: null });
                }
            }
        });

        if (subjectSelect) {
            subjectSelect.addEventListener('change', function() {
                if (onChange) {
                    onChange({
                        classId: classSelect.value,
                        sectionId: sectionSelect.value,
                        subjectId: this.value
                    });
                }
            });
        }
    }

    // =========================================================================
    // MODAL UTILITIES
    // =========================================================================

    /**
     * Open modal
     */
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }

    /**
     * Close modal
     */
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        state.editMode = { type: null, id: null };
    }

    /**
     * Show confirm dialog
     */
    function showConfirmDialog(title, message, type = 'warning') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay confirm-dialog';
            modal.innerHTML = `
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header modal-header-${type}">
                            <h5 class="modal-title">
                                <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'question-circle'}"></i>
                                ${title}
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-action="cancel">Cancel</button>
                            <button class="btn btn-${type}" data-action="confirm">Confirm</button>
                        </div>
                    </div>
                </div>
            `;

            modal.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action === 'confirm') {
                    resolve(true);
                    modal.remove();
                } else if (action === 'cancel' || e.target === modal) {
                    resolve(false);
                    modal.remove();
                }
            });

            document.body.appendChild(modal);
        });
    }

    // =========================================================================
    // FORM HANDLERS
    // =========================================================================

    /**
     * Handle class form submission
     */
    function handleClassFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        const classData = {
            class_name: formData.get('class_name'),
            numeric_value: parseInt(formData.get('numeric_value')),
            status: formData.get('status') || 'Active'
        };

        // Parse initial sections if provided (for new class)
        if (!state.editMode.id && formData.get('initial_sections')) {
            classData.initial_sections = formData.get('initial_sections')
                .split(',')
                .map(s => s.trim())
                .filter(s => s);
        }

        if (state.editMode.id) {
            updateClass(state.editMode.id, classData);
        } else {
            addClass(classData);
        }
    }

    /**
     * Handle section form submission
     */
    function handleSectionFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        const sectionData = {
            class_id: parseInt(formData.get('class_id')),
            section_name: formData.get('section_name'),
            capacity: parseInt(formData.get('capacity')) || 40,
            room_number: formData.get('room_number') || null,
            status: formData.get('status') || 'Active'
        };

        if (state.editMode.id) {
            updateSection(state.editMode.id, sectionData);
        } else {
            addSection(sectionData);
        }
    }

    /**
     * Handle subject form submission
     */
    function handleSubjectFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        const subjectData = {
            subject_code: formData.get('subject_code'),
            subject_name: formData.get('subject_name'),
            short_name: formData.get('short_name') || null,
            subject_type: formData.get('subject_type') || 'Theory',
            max_marks: parseInt(formData.get('max_marks')) || 100,
            pass_marks: parseInt(formData.get('pass_marks')) || 33,
            color_code: formData.get('color_code') || '#6366F1',
            status: formData.get('status') || 'Active'
        };

        if (state.editMode.id) {
            updateSubject(state.editMode.id, subjectData);
        } else {
            addSubject(subjectData);
        }
    }

    /**
     * Handle class-subjects form submission
     */
    function handleClassSubjectsFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const classId = parseInt(form.dataset.classId);
        const checkboxes = form.querySelectorAll('input[name="subjects"]:checked');
        const subjectIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        saveClassSubjects(classId, subjectIds);
    }

    // =========================================================================
    // SEARCH & FILTER
    // =========================================================================

    /**
     * Filter classes table
     */
    const filterClasses = debounce(function(searchTerm) {
        const tbody = document.getElementById('classesTableBody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr[data-class-id]');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const className = row.querySelector('.class-name')?.textContent.toLowerCase() || '';
            const sections = row.querySelector('.sections-badges')?.textContent.toLowerCase() || '';

            if (className.includes(term) || sections.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }, CONFIG.debounceDelay);

    /**
     * Filter subjects table
     */
    const filterSubjects = debounce(function(searchTerm) {
        const tbody = document.getElementById('subjectsTableBody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr[data-subject-id]');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const code = row.querySelector('.subject-code')?.textContent.toLowerCase() || '';
            const name = row.querySelector('.subject-name')?.textContent.toLowerCase() || '';

            if (code.includes(term) || name.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }, CONFIG.debounceDelay);

    // =========================================================================
    // HTML ESCAPE UTILITY
    // =========================================================================

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    /**
     * Initialize the module
     */
    async function init() {
        console.log('Initializing Academic Settings Module...');

        // Setup form handlers
        const classForm = document.getElementById('classForm');
        if (classForm) {
            classForm.addEventListener('submit', handleClassFormSubmit);
        }

        const sectionForm = document.getElementById('sectionForm');
        if (sectionForm) {
            sectionForm.addEventListener('submit', handleSectionFormSubmit);
        }

        const subjectForm = document.getElementById('subjectForm');
        if (subjectForm) {
            subjectForm.addEventListener('submit', handleSubjectFormSubmit);
        }

        const classSubjectsForm = document.getElementById('classSubjectsForm');
        if (classSubjectsForm) {
            classSubjectsForm.addEventListener('submit', handleClassSubjectsFormSubmit);
        }

        // Setup search inputs
        const classSearch = document.getElementById('classSearchInput');
        if (classSearch) {
            classSearch.addEventListener('input', (e) => filterClasses(e.target.value));
        }

        const subjectSearch = document.getElementById('subjectSearchInput');
        if (subjectSearch) {
            subjectSearch.addEventListener('input', (e) => filterSubjects(e.target.value));
        }

        // Setup modal close buttons
        document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay, .modal');
                if (modal) {
                    closeModal(modal.id);
                }
            });
        });

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // Load initial data
        await Promise.all([
            loadClasses(),
            loadSubjects()
        ]);

        console.log('Academic Settings Module initialized successfully!');
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    return {
        // Initialization
        init,

        // Classes
        loadClasses,
        addClass,
        updateClass,
        deleteClass,
        editClass,
        showClassModal,

        // Sections
        loadSectionsForClass,
        addSection,
        updateSection,
        deleteSection,
        editSection,
        showAddSectionModal,

        // Subjects
        loadSubjects,
        loadSubjectsForClass,
        addSubject,
        updateSubject,
        deleteSubject,
        editSubject,
        showSubjectModal,

        // Class-Subject Assignments
        manageClassSubjects,
        saveClassSubjects,

        // Dependent Dropdowns (for other modules)
        populateClassDropdown,
        populateSectionDropdown,
        populateSubjectDropdown,
        setupClassSectionChain,
        setupFullChain,

        // Utilities
        openModal,
        closeModal,
        showToast,

        // State access
        getState: () => ({ ...state }),
        getClasses: () => [...state.classes],
        getSubjects: () => [...state.subjects]
    };

})();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on settings page
    if (document.getElementById('academicSettingsContainer') ||
        document.getElementById('classesTableBody')) {
        AcademicSettings.init();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AcademicSettings;
}
