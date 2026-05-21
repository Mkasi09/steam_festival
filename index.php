<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'School & Learner Registration';
$pdo = db();

function learnerIsValid(array $learner): bool
{
    return trim($learner['first_name'] ?? '') !== ''
        && trim($learner['last_name'] ?? '') !== ''
        && trim($learner['grade'] ?? '') !== '';
}

function teacherIsValid(array $teacher): bool
{
    return trim($teacher['first_name'] ?? '') !== ''
        && trim($teacher['last_name'] ?? '') !== '';
}

function cleanGender(?string $gender): ?string
{
    $gender = trim((string) $gender);
    return in_array($gender, ['Female', 'Male', 'Other'], true) ? $gender : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_school') {
        $name = trim($_POST['name'] ?? '');
        $emisNumber = trim($_POST['emis_number'] ?? '');
        $districtId = (int) ($_POST['district_id'] ?? 0);
        $circuitId = (int) ($_POST['circuit_id'] ?? 0);
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '' || $districtId < 1 || $circuitId < 1) {
            flash('School name, district, and circuit are required.', 'error');
            redirect('index.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO schools (name, emis_number, district_id, circuit_id, contact_person, phone, email, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $emisNumber ?: null,
            $districtId,
            $circuitId,
            $contactPerson ?: null,
            formatPhone($phone) ?: null,
            $email ?: null,
            $address ?: null,
        ]);

        flash('School added. You can register learners now.');
        redirect('index.php?school_id=' . (int) $pdo->lastInsertId());
    }

    if ($action === 'add_learners') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $learners = $_POST['learners'] ?? [];
        $inserted = 0;

        if ($schoolId < 1) {
            flash('Choose a school before registering learners.', 'error');
            redirect('index.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO learners (school_id, first_name, last_name, race, grade, gender)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($learners as $learner) {
            if (!is_array($learner) || !learnerIsValid($learner)) {
                continue;
            }

            $stmt->execute([
                $schoolId,
                trim($learner['first_name']),
                trim($learner['last_name']),
                trim($learner['race'] ?? '') ?: null,
                trim($learner['grade']),
                cleanGender($learner['gender'] ?? null),
            ]);
            $inserted++;
        }

        flash($inserted . ' learner' . ($inserted === 1 ? '' : 's') . ' registered.');
        redirect('index.php?school_id=' . $schoolId);
    }

    if ($action === 'update_learner') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $learnerId = (int) ($_POST['learner_id'] ?? 0);
        $learner = $_POST['learner'] ?? [];

        if ($schoolId < 1 || $learnerId < 1 || !is_array($learner) || !learnerIsValid($learner)) {
            flash('Name, surname, and grade are required to update a learner.', 'error');
            redirect('index.php?school_id=' . max(0, $schoolId));
        }

        $stmt = $pdo->prepare(
            'UPDATE learners
             SET first_name = ?, last_name = ?, race = ?, grade = ?, gender = ?
             WHERE id = ? AND school_id = ?'
        );
        $stmt->execute([
            trim($learner['first_name']),
            trim($learner['last_name']),
            trim($learner['race'] ?? '') ?: null,
            trim($learner['grade']),
            cleanGender($learner['gender'] ?? null),
            $learnerId,
            $schoolId,
        ]);

        flash('Learner updated.');
        redirect('index.php?school_id=' . $schoolId);
    }

    if ($action === 'delete_learner') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $learnerId = (int) ($_POST['learner_id'] ?? 0);

        if ($schoolId < 1 || $learnerId < 1) {
            flash('Invalid request.', 'error');
            redirect('index.php?school_id=' . max(0, $schoolId));
        }

        $stmt = $pdo->prepare('DELETE FROM learners WHERE id = ? AND school_id = ?');
        $stmt->execute([$learnerId, $schoolId]);

        flash('Learner deleted.');
        redirect('index.php?school_id=' . $schoolId);
    }

    if ($action === 'add_teachers') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $teachers = $_POST['teachers'] ?? [];
        $inserted = 0;

        if ($schoolId < 1) {
            flash('Choose a school before registering teachers.', 'error');
            redirect('index.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO teachers (school_id, first_name, last_name, subject, race, gender, email, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($teachers as $teacher) {
            if (!is_array($teacher) || !teacherIsValid($teacher)) {
                continue;
            }

            $stmt->execute([
                $schoolId,
                trim($teacher['first_name']),
                trim($teacher['last_name']),
                trim($teacher['subject'] ?? '') ?: null,
                trim($teacher['race'] ?? '') ?: null,
                cleanGender($teacher['gender'] ?? null),
                trim($teacher['email'] ?? '') ?: null,
                formatPhone(trim($teacher['phone'] ?? '')) ?: null,
            ]);
            $inserted++;
        }

        flash($inserted . ' teacher' . ($inserted === 1 ? '' : 's') . ' registered.');
        redirect('index.php?school_id=' . $schoolId);
    }

    if ($action === 'update_teacher') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $teacher = $_POST['teacher'] ?? [];

        if ($schoolId < 1 || $teacherId < 1 || !is_array($teacher) || !teacherIsValid($teacher)) {
            flash('Name and surname are required to update a teacher.', 'error');
            redirect('index.php?school_id=' . max(0, $schoolId));
        }

        $stmt = $pdo->prepare(
            'UPDATE teachers
             SET first_name = ?, last_name = ?, subject = ?, race = ?, gender = ?, email = ?, phone = ?
             WHERE id = ? AND school_id = ?'
        );
        $stmt->execute([
            trim($teacher['first_name']),
            trim($teacher['last_name']),
            trim($teacher['subject'] ?? '') ?: null,
            trim($teacher['race'] ?? '') ?: null,
            cleanGender($teacher['gender'] ?? null),
            trim($teacher['email'] ?? '') ?: null,
            formatPhone(trim($teacher['phone'] ?? '')) ?: null,
            $teacherId,
            $schoolId,
        ]);

        flash('Teacher updated.');
        redirect('index.php?school_id=' . $schoolId);
    }

    if ($action === 'delete_teacher') {
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);

        if ($schoolId < 1 || $teacherId < 1) {
            flash('Invalid request.', 'error');
            redirect('index.php?school_id=' . max(0, $schoolId));
        }

        $stmt = $pdo->prepare('DELETE FROM teachers WHERE id = ? AND school_id = ?');
        $stmt->execute([$teacherId, $schoolId]);

        flash('Teacher deleted.');
        redirect('index.php?school_id=' . $schoolId);
    }
}

$selectedSchoolId = (int) ($_GET['school_id'] ?? 0);
$schools = $pdo->query(
    'SELECT s.id, s.name, s.emis_number, d.name AS district, c.name AS circuit
     FROM schools s
     LEFT JOIN districts d ON d.id = s.district_id
     LEFT JOIN circuits c ON c.id = s.circuit_id
     ORDER BY s.name'
)->fetchAll();
$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
$circuits = $pdo->query('SELECT id, district_id, name FROM circuits ORDER BY name')->fetchAll();

$selectedSchool = null;
$registeredTeachers = [];
$registeredLearners = [];

if ($selectedSchoolId > 0) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.emis_number, s.contact_person, s.phone, s.email, s.address,
                d.name AS district, c.name AS circuit
         FROM schools s
         LEFT JOIN districts d ON d.id = s.district_id
         LEFT JOIN circuits c ON c.id = s.circuit_id
         WHERE s.id = ?'
    );
    $stmt->execute([$selectedSchoolId]);
    $selectedSchool = $stmt->fetch();

    if ($selectedSchool) {
        $stmt = $pdo->prepare(
            'SELECT id, first_name, last_name, subject, race, gender, email, phone
             FROM teachers
             WHERE school_id = ?
             ORDER BY id DESC'
        );
        $stmt->execute([$selectedSchoolId]);
        $registeredTeachers = $stmt->fetchAll();

       $stmt = $pdo->prepare(
    'SELECT id, first_name, last_name, race, grade, gender
     FROM learners
     WHERE school_id = ?
     ORDER BY 
        CASE 
            WHEN grade = "R" THEN 0
            ELSE CAST(grade AS UNSIGNED)
        END ASC,
        first_name ASC'
);
        $stmt->execute([$selectedSchoolId]);
        $registeredLearners = $stmt->fetchAll();
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Welcome to the STEAM Festival 2026 registration!</p>
        <p>Choose an existing school from the list. If it is not there yet, add it once and continue with learner registration.</p>
    </div>
    <div class="school-summary">
        <p class="eyebrow">Selected school</p>
        <?php if ($selectedSchool): ?>
            <h3><?= e($selectedSchool['name']) ?></h3>
            <dl>
                <div>
                    <dt>EMIS</dt>
                    <dd><?= e($selectedSchool['emis_number'] ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>District</dt>
                    <dd><?= e($selectedSchool['district'] ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>Circuit</dt>
                    <dd><?= e($selectedSchool['circuit'] ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>Contact</dt>
                    <dd><?= e($selectedSchool['contact_person'] ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>Phone</dt>
                    <dd><?= e(formatPhone($selectedSchool['phone']) ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd><?= e($selectedSchool['email'] ?: 'Not captured') ?></dd>
                </div>
                <div>
                    <dt>Address</dt>
                    <dd><?= e($selectedSchool['address'] ?: 'Not captured') ?></dd>
                </div>
            </dl>
        <?php else: ?>
            <h3>No school selected</h3>
            <p>Select a school below to view its details and register learners.</p>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">School</p>
            <h2>Select school</h2>
           <p>Select a school first, or add it if it is not in the dropdown.</p>
        </div>
    </div>

    <form method="get" class="school-picker">
        <label>
            Search school
            <input type="search" id="school-search" placeholder="Type school name, EMIS, district, or circuit">
            <div id="school-search-results" class="school-results" aria-live="polite"></div>
        </label>
        <label>
            School
            <select name="school_id" id="school-picker" required>
                <option value="">Select school</option>
                <?php foreach ($schools as $school): ?>
                    <?php
                    $schoolOptionText = trim($school['name'] . ' ' . $school['emis_number'] . ' ' . $school['district'] . ' ' . $school['circuit']);
                    ?>
                    <option
                        value="<?= (int) $school['id'] ?>"
                        data-search="<?= e(strtolower($schoolOptionText)) ?>"
                        <?= $selectedSchoolId === (int) $school['id'] ? 'selected' : '' ?>
                    >
                        <?= e($school['name']) ?><?= $school['emis_number'] ? ' - ' . e($school['emis_number']) : '' ?><?= $school['district'] ? ' (' . e($school['district']) : '' ?><?= $school['circuit'] ? ' / ' . e($school['circuit']) : '' ?><?= $school['district'] ? ')' : '' ?>
                    </option>
                <?php endforeach; ?>
                <option value="new">School not found - add new</option>
            </select>
        </label>
        <button class="button secondary" type="submit" id="load-school">Select School</button>

    </form>

    <form method="post" class="form add-school-panel" id="add-school-panel">
        <input type="hidden" name="action" value="add_school">
        <div class="panel-heading tight">
            <div>
                <p class="eyebrow">New school</p>
                <h2>Add school</h2>
            </div>
        </div>
        <div class="grid two">
            <label>
                School name
                <input name="name">
            </label>
            <label>
                EMIS number
                <input name="emis_number">
            </label>
            <label>
                District
                <select name="district_id" id="district-picker">
                    <option value="">Select district</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int) $district['id'] ?>"><?= e($district['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Circuit
                <select name="circuit_id" id="circuit-picker">
                    <option value="">Select circuit</option>
                    <?php foreach ($circuits as $circuit): ?>
                        <option value="<?= (int) $circuit['id'] ?>" data-district="<?= (int) $circuit['district_id'] ?>"><?= e($circuit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Contact person
                <input name="contact_person">
            </label>
            <label>
                Phone
                <input name="phone">
            </label>
            <label>
                Email
                <input type="email" name="email">
            </label>
            <label>
                Address
                <input name="address">
            </label>
        </div>
        <button class="button" type="submit">Add school</button>
    </form>
</section>
<?php if (!$selectedSchool): ?>
    <?php else: ?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Registration</p>
            <h2><?= $selectedSchool ? 'Register for ' . e($selectedSchool['name']) : 'Register' ?></h2>
        </div>
    </div>

    
       
    
        <div class="registration-tabs">
           <div class="tabs-nav">

    <button
        type="button"
        class="tab-button active"
        data-tab="teachers-tab"
    >
        TEACHERS
    </button>

    <button
        type="button"
        class="tab-button"
        data-tab="learners-tab"
    >
        LEARNERS
    </button>

</div>

            <div id="teachers-tab" class="tab-content active">
                <div class="panel-heading tight">
                    <div>
                        <p class="eyebrow">Teachers</p>
                        <h3>STEAM Festival Teacher Registration</h3>
                    </div>
                    <button class="button secondary" type="button" id="add-teacher-row">Add teacher row</button>
                </div>
                <form method="post" class="form">
                    <input type="hidden" name="action" value="add_teachers">
                    <input type="hidden" name="school_id" value="<?= (int) $selectedSchool['id'] ?>">

                    <div class="table-wrap">
                        <table id="teacher-table" class="entry-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Surname</th>
                                    <th>Subject</th>
                                    <th>Race</th>
                                    <th>Gender</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <button class="button" type="submit">Save teachers</button>
                </form>
            </div>

            <div id="learners-tab" class="tab-content">
                <div class="panel-heading tight">
                    <div>
                        <p class="eyebrow">Learners</p>
                        <h3>Register learners for <?= e($selectedSchool['name']) ?></h3>
                    </div>
                    <button class="button secondary" type="button" id="add-row">Add learner row</button>
                </div>
                <form method="post" class="form">
                    <input type="hidden" name="action" value="add_learners">
                    <input type="hidden" name="school_id" value="<?= (int) $selectedSchool['id'] ?>">

                    <div class="table-wrap">
                        <table id="learner-table" class="entry-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Surname</th>
                                    <th>Race</th>
                                    <th>Grade</th>
                                    <th>Gender</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <button class="button" type="submit">Save learners</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($selectedSchool): ?>

    <!-- SAVED TEACHERS -->
    <section class="panel saved-section" id="saved-teachers-section">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Saved teachers</p>
                <h2>Edit registered teachers</h2>
            </div>
        </div>

        <?php if (!$registeredTeachers): ?>
            <p class="empty">No teachers registered for this school yet.</p>
        <?php else: ?>

            <div class="edit-list">
                <?php foreach ($registeredTeachers as $teacher): ?>
                    <?php
                    $initials = strtoupper(
                        substr((string)$teacher['first_name'], 0, 1) .
                        substr((string)$teacher['last_name'], 0, 1)
                    );
                    ?>

                    <form method="post" class="edit-card">

                        <input type="hidden" name="school_id" value="<?= (int)$selectedSchool['id'] ?>">
                        <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id'] ?>">

                        <div class="learner-card-head">
                            <div class="avatar"><?= e($initials ?: 'TR') ?></div>

                            <div class="learner-summary">
                                <strong>
                                    <?= e($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="edit-fields">

                            <label>
                                Name
                                <input
                                    name="teacher[first_name]"
                                    value="<?= e($teacher['first_name']) ?>"
                                    required
                                >
                            </label>

                            <label>
                                Surname
                                <input
                                    name="teacher[last_name]"
                                    value="<?= e($teacher['last_name']) ?>"
                                    required
                                >
                            </label>

                            <label>
                                Subject
                                <input
                                    name="teacher[subject]"
                                    value="<?= e($teacher['subject']) ?>"
                                >
                            </label>

                            <label>
                                Race
                                <select name="teacher[race]">
                                    <?php foreach (
                                        [
                                            '' => 'Select',
                                            'African' => 'African',
                                            'Coloured' => 'Coloured',
                                            'Indian' => 'Indian',
                                            'White' => 'White',
                                            'Other' => 'Other'
                                        ] as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $teacher['race'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= e($label) ?>
                                        </option>

                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Gender
                                <select name="teacher[gender]">

                                    <?php foreach (
                                        [
                                            '' => 'Select',
                                            'Female' => 'Female',
                                            'Male' => 'Male',
                                            'Other' => 'Other'
                                        ] as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $teacher['gender'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= e($label) ?>
                                        </option>

                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Email
                                <input
                                    type="email"
                                    name="teacher[email]"
                                    value="<?= e($teacher['email']) ?>"
                                >
                            </label>

                            <label>
                                Phone
                                <input
                                    name="teacher[phone]"
                                    value="<?= e($teacher['phone']) ?>"
                                >
                            </label>

                            <div class="actions">

                                <button
                                    class="button secondary"
                                    type="submit"
                                    name="action"
                                    value="update_teacher"
                                >
                                    UPDATE
                                </button>

                                <button
                                    class="icon-button delete"
                                    type="submit"
                                    name="action"
                                    value="delete_teacher"
                                    aria-label="Delete teacher"
                                >
                                    ✕
                                </button>

                            </div>

                        </div>

                    </form>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </section>



    <!-- SAVED LEARNERS -->
    <section class="panel saved-section" id="saved-learners-section">

        <div class="panel-heading">
            <div>
                <p class="eyebrow">Saved learners</p>
                <h2>Edit registered learners</h2>
            </div>
        </div>

        <?php if (!$registeredLearners): ?>
            <p class="empty">No learners registered for this school yet.</p>
        <?php else: ?>

            <div class="edit-list">

                <?php foreach ($registeredLearners as $learner): ?>

                    <?php
                    $initials = strtoupper(
                        substr((string)$learner['first_name'], 0, 1) .
                        substr((string)$learner['last_name'], 0, 1)
                    );
                    ?>

                    <form method="post" class="edit-card">

                        <input type="hidden" name="school_id" value="<?= (int)$selectedSchool['id'] ?>">
                        <input type="hidden" name="learner_id" value="<?= (int)$learner['id'] ?>">

                        <div class="learner-card-head">

                            <div class="avatar"><?= e($initials ?: 'LR') ?></div>

                            <div class="learner-summary">
                                <strong>
                                    <?= e($learner['first_name'] . ' ' . $learner['last_name']) ?>
                                </strong>
                            </div>

                        </div>

                        <div class="edit-fields">

                            <label>
                                Name
                                <input
                                    name="learner[first_name]"
                                    value="<?= e($learner['first_name']) ?>"
                                    required
                                >
                            </label>

                            <label>
                                Surname
                                <input
                                    name="learner[last_name]"
                                    value="<?= e($learner['last_name']) ?>"
                                    required
                                >
                            </label>

                            <label>
                                Race
                                <select name="learner[race]">

                                    <?php foreach (
                                        [
                                            '' => 'Select',
                                            'African' => 'African',
                                            'Coloured' => 'Coloured',
                                            'Indian' => 'Indian',
                                            'White' => 'White',
                                            'Other' => 'Other'
                                        ] as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $learner['race'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= e($label) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </label>

                            <label>
                                Grade
                                <input
                                    name="learner[grade]"
                                    value="<?= e($learner['grade']) ?>"
                                    required
                                >
                            </label>

                            <label>
                                Gender
                                <select name="learner[gender]">

                                    <?php foreach (
                                        [
                                            '' => 'Select',
                                            'Female' => 'Female',
                                            'Male' => 'Male',
                                            'Other' => 'Other'
                                        ] as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= $learner['gender'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= e($label) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </label>

                            <div class="actions">

                                <button
                                    class="button secondary"
                                    type="submit"
                                    name="action"
                                    value="update_learner"
                                >
                                    UPDATE
                                </button>

                                <button
                                    class="icon-button delete"
                                    type="submit"
                                    name="action"
                                    value="delete_learner"
                                >
                                    ✕
                                </button>

                            </div>

                        </div>

                    </form>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

<?php endif; ?>

<template id="learner-row-template">
    <tr>
        <td><input name="learners[__i__][first_name]" required></td>
        <td><input name="learners[__i__][last_name]" required></td>
        <td>
            <select name="learners[__i__][race]">
                <option value="">Select</option>
                <option>African</option>
                <option>Coloured</option>
                <option>Indian</option>
                <option>White</option>
                <option>Other</option>
            </select>
        </td>
        <td>
            <select name="learners[__i__][grade]" required>
                <option value="">Select</option>
                <option>R</option>
                <option>0</option>
                <option>1</option>
                <option>2</option>
                <option>3</option>
                <option>4</option>
                <option>5</option>
                <option>6</option>
                <option>7</option>
                <option>8</option>
                <option>9</option>
                <option>10</option>
                <option>11</option>
                <option>12</option>
            </select>
        </td>
        <td>
            <select name="learners[__i__][gender]">
                <option value="">Select</option>
                <option>Female</option>
                <option>Male</option>
                <option>Other</option>
            </select>
        </td>
        <td><button class="icon-button" type="button" aria-label="Remove row">x</button></td>
    </tr>
</template>

<template id="teacher-row-template">
    <tr>
        <td><input name="teachers[__i__][first_name]" required></td>
        <td><input name="teachers[__i__][last_name]" required></td>
        <td><input name="teachers[__i__][subject]"></td>
        <td>
            <select name="teachers[__i__][race]">
                <option value="">Select</option>
                <option>African</option>
                <option>Coloured</option>
                <option>Indian</option>
                <option>White</option>
                <option>Other</option>
            </select>
        </td>
        <td>
            <select name="teachers[__i__][gender]">
                <option value="">Select</option>
                <option>Female</option>
                <option>Male</option>
                <option>Other</option>
            </select>
        </td>
        <td><input type="email" name="teachers[__i__][email]"></td>
        <td><input name="teachers[__i__][phone]"></td>
        <td><button class="icon-button" type="button" aria-label="Remove row">x</button></td>
    </tr>
</template>
<script>

// Saved sections
const savedTeachersSection = document.getElementById('saved-teachers-section');
const savedLearnersSection = document.getElementById('saved-learners-section');

// Show correct saved section
function switchSavedSections(activeTab) {

    if (!savedTeachersSection || !savedLearnersSection) {
        return;
    }

    if (activeTab === 'teachers-tab') {

        savedTeachersSection.style.display = 'block';
        savedLearnersSection.style.display = 'none';

    } else {

        savedTeachersSection.style.display = 'none';
        savedLearnersSection.style.display = 'block';

    }
}

</script>
<script>
    
const schoolPicker = document.querySelector('#school-picker');
const schoolSearch = document.querySelector('#school-search');
const schoolResults = document.querySelector('#school-search-results');
const schoolOptions = schoolPicker ? [...schoolPicker.querySelectorAll('option')] : [];
const loadSchoolButton = document.querySelector('#load-school');
const addSchoolPanel = document.querySelector('#add-school-panel');
const newSchoolName = addSchoolPanel?.querySelector('input[name="name"]');
const districtPicker = document.querySelector('#district-picker');
const circuitPicker = document.querySelector('#circuit-picker');
const circuitOptions = circuitPicker ? [...circuitPicker.querySelectorAll('option')] : [];

// Teacher elements
const teacherTableBody = document.querySelector('#teacher-table tbody');
const teacherTemplate = document.querySelector('#teacher-row-template');
const addTeacherRowButton = document.querySelector('#add-teacher-row');
const teacherSearch = document.querySelector('#teacher-search');
const teacherCards = [...document.querySelectorAll('[data-teacher-card]')];
const teacherSearchCount = document.querySelector('#teacher-search-count');
const teacherNoResults = document.querySelector('#teacher-no-results');

// Learner elements
const tableBody = document.querySelector('#learner-table tbody');
const template = document.querySelector('#learner-row-template');
const addRowButton = document.querySelector('#add-row');
const learnerSearch = document.querySelector('#learner-search');
const learnerCards = [...document.querySelectorAll('[data-learner-card]')];
const learnerSearchCount = document.querySelector('#learner-search-count');
const learnerNoResults = document.querySelector('#learner-no-results');

// Tab elements
const tabButtons = [...document.querySelectorAll('.tab-button')];
const tabContents = [...document.querySelectorAll('.tab-content')];

let rowIndex = 0;

function toggleAddSchoolPanel() {
    const addingNew = schoolPicker.value === 'new';
    addSchoolPanel.classList.toggle('visible', addingNew);
    loadSchoolButton.disabled = addingNew;
    if (newSchoolName) {
        newSchoolName.required = addingNew;
    }
    if (districtPicker && circuitPicker) {
        districtPicker.required = addingNew;
        circuitPicker.required = addingNew;
    }
}

function filterCircuits() {
    if (!districtPicker || !circuitPicker) {
        return;
    }

    const districtId = districtPicker.value;
    circuitPicker.value = '';
    circuitOptions.forEach((option) => {
        option.hidden = option.value !== '' && option.dataset.district !== districtId;
    });
}

function addTeacherRow() {
    if (!teacherTableBody || !teacherTemplate) {
        return;
    }

    teacherTableBody.insertAdjacentHTML('beforeend', teacherTemplate.innerHTML.replaceAll('__i__', rowIndex++));
}

function addLearnerRow() {
    if (!tableBody || !template) {
        return;
    }

    tableBody.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__i__', rowIndex++));
}


tabButtons.forEach((button) => {

    button.addEventListener('click', () => {

        const targetTab = button.dataset.tab;

        // Remove active class from all buttons
        tabButtons.forEach((btn) => {
            btn.classList.remove('active');
        });

        // Hide all tab contents
        tabContents.forEach((content) => {
            content.classList.remove('active');
        });

        // Activate selected button
        button.classList.add('active');

        // Show selected tab
        document.getElementById(targetTab).classList.add('active');

        // SWITCH SAVED SECTIONS
        switchSavedSections(targetTab);

    });

});

schoolPicker.addEventListener('change', toggleAddSchoolPanel);
schoolSearch?.addEventListener('input', () => {
    const query = schoolSearch.value.trim().toLowerCase();
    schoolResults.innerHTML = '';
    schoolResults.classList.remove('visible');

    schoolOptions.forEach((option) => {
        if (option.value === '' || option.value === 'new') {
            option.hidden = false;
            return;
        }
        option.hidden = query !== '' && !option.dataset.search.includes(query);
    });

    if (query.length < 2) {
        return;
    }

    const matches = schoolOptions
        .filter((option) => option.value !== '' && option.value !== 'new' && !option.hidden)
        .slice(0, 8);

    if (matches.length === 0) {
        schoolResults.innerHTML = '<div class="school-result-item muted">No matching school found</div>';
        schoolResults.classList.add('visible');
        return;
    }

    matches.forEach((option) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'school-result-item';
        button.textContent = option.textContent.trim();
        button.addEventListener('click', () => {
            schoolPicker.value = option.value;
            schoolResults.classList.remove('visible');
            schoolSearch.value = option.textContent.trim();
            schoolPicker.form.submit();
        });
        schoolResults.appendChild(button);
    });

    schoolResults.classList.add('visible');
});

document.addEventListener('click', (event) => {
    if (!schoolResults || !schoolSearch) {
        return;
    }
    if (!schoolResults.contains(event.target) && event.target !== schoolSearch) {
        schoolResults.classList.remove('visible');
    }
});

districtPicker?.addEventListener('change', filterCircuits);
toggleAddSchoolPanel();
filterCircuits();
switchSavedSections('teachers-tab');
// Teacher row management
if (addTeacherRowButton && teacherTableBody) {
    addTeacherRowButton.addEventListener('click', addTeacherRow);
    teacherTableBody.addEventListener('click', (event) => {
        if (event.target.matches('.icon-button')) {
            event.target.closest('tr').remove();
        }
    });

    addTeacherRow();
}

// Learner row management
if (addRowButton && tableBody) {
    addRowButton.addEventListener('click', addLearnerRow);
    tableBody.addEventListener('click', (event) => {
        if (event.target.matches('.icon-button')) {
            event.target.closest('tr').remove();
        }
    });

    addLearnerRow();
}

// Teacher search
if (teacherSearch) {
    teacherSearch.addEventListener('input', () => {
        const query = teacherSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        teacherCards.forEach((card) => {
            const visible = card.dataset.search.includes(query);
            card.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        teacherSearchCount.textContent = visibleCount + ' teacher' + (visibleCount === 1 ? '' : 's');
        teacherNoResults.classList.toggle('visible', visibleCount === 0);
    });
}

// Learner search
if (learnerSearch) {
    learnerSearch.addEventListener('input', () => {
        const query = learnerSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        learnerCards.forEach((card) => {
            const visible = card.dataset.search.includes(query);
            card.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        learnerSearchCount.textContent = visibleCount + ' learner' + (visibleCount === 1 ? '' : 's');
        learnerNoResults.classList.toggle('visible', visibleCount === 0);
    });
}
</script>

<script>
// Confirm before deleting
document.querySelectorAll('.icon-button.delete').forEach((btn) => {
    btn.addEventListener('click', (e) => {
        if (!confirm('Delete this person? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
