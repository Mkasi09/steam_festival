<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Learner Entries';
$pdo = db();
$perPage = 80;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$query = trim($_GET['q'] ?? '');
$schoolFilter = max(0, (int) ($_GET['school_id'] ?? 0));
$genderFilter = trim($_GET['gender'] ?? '');
$gradeFilter = trim($_GET['grade'] ?? '');
$editId = max(0, (int) ($_GET['edit_id'] ?? 0));

if (!in_array($genderFilter, ['', 'Female', 'Male', 'Other'], true)) {
    $genderFilter = '';
}

function validLearnerUpdate(array $row): bool
{
    return trim($row['first_name'] ?? '') !== ''
        && trim($row['last_name'] ?? '') !== ''
    && trim($row['grade'] ?? '') !== ''
    && in_array(trim($row['gender'] ?? ''), ['Female', 'Male', 'Other'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnQuery = trim($_POST['return_query'] ?? '');
    $returnUrl = 'admin_learners.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');

    if ($action === 'update_learner') {
        $id = (int) ($_POST['id'] ?? 0);
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $learner = $_POST['learner'] ?? [];

        if ($id < 1 || $schoolId < 1 || !is_array($learner) || !validLearnerUpdate($learner)) {
            flash('Name, surname, grade, and school are required.', 'error');
            redirect($returnUrl);
        }

        $gender = trim($learner['gender'] ?? '');
        if (!in_array($gender, ['Female', 'Male', 'Other'], true)) {
            $gender = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE learners
             SET school_id = ?, first_name = ?, last_name = ?, race = ?, grade = ?, gender = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $schoolId,
            trim($learner['first_name']),
            trim($learner['last_name']),
            trim($learner['race'] ?? '') ?: null,
            trim($learner['grade']),
            $gender,
            $id,
        ]);
        flash('Learner updated.');
        redirect($returnUrl);
    }

    if ($action === 'delete_learner') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM learners WHERE id = ?');
            $stmt->execute([$id]);
            flash('Learner deleted.');
        }
        redirect($returnUrl);
    }
}

$schools = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll();
$grades = $pdo->query('SELECT DISTINCT grade FROM learners WHERE grade IS NOT NULL AND grade <> "" ORDER BY grade')->fetchAll(PDO::FETCH_COLUMN);
$filters = [];
$params = [];

if ($query !== '') {
    $filters[] = '(l.first_name LIKE :q OR l.last_name LIKE :q OR s.name LIKE :q OR l.race LIKE :q OR l.grade LIKE :q)';
    $params[':q'] = '%' . $query . '%';
}

if ($schoolFilter > 0) {
    $filters[] = 'l.school_id = :school_id';
    $params[':school_id'] = $schoolFilter;
}

if ($genderFilter !== '') {
    $filters[] = 'l.gender = :gender';
    $params[':gender'] = $genderFilter;
}

if ($gradeFilter !== '') {
    $filters[] = 'l.grade = :grade';
    $params[':grade'] = $gradeFilter;
}

$whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM learners l
     LEFT JOIN schools s ON s.id = l.school_id
     ' . $whereClause
);
foreach ($params as $name => $value) {
    $countStmt->bindValue($name, $value, $name === ':school_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT l.id, l.school_id, l.first_name, l.last_name, l.race, l.grade, l.gender, s.name AS school_name
     FROM learners l
     LEFT JOIN schools s ON s.id = l.school_id
     ' . $whereClause . '
     ORDER BY l.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, $name === ':school_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$learners = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));
$pageQuery = http_build_query([
    'q' => $query,
    'school_id' => $schoolFilter ?: null,
    'gender' => $genderFilter ?: null,
    'grade' => $gradeFilter ?: null,
]);
$pageQueryWithPage = http_build_query([
    'page' => $page,
    'q' => $query,
    'school_id' => $schoolFilter ?: null,
    'gender' => $genderFilter ?: null,
    'grade' => $gradeFilter ?: null,
]);
$selectedLearner = null;

if ($editId > 0) {
    $editStmt = $pdo->prepare(
        'SELECT l.id, l.school_id, l.first_name, l.last_name, l.race, l.grade, l.gender, s.name AS school_name
         FROM learners l
         LEFT JOIN schools s ON s.id = l.school_id
         WHERE l.id = ?'
    );
    $editStmt->execute([$editId]);
    $selectedLearner = $editStmt->fetch();
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>Learner Entries</h2>
        </div>
    </div>

    <form method="get" class="learner-filter">
        <label>
            Search learners
            <input name="q" value="<?= e($query) ?>" placeholder="Search by learner, school, race, or grade">
        </label>
        <label>
            School
            <select name="school_id">
                <option value="">All schools</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= (int) $school['id'] ?>" <?= $schoolFilter === (int) $school['id'] ? 'selected' : '' ?>>
                        <?= e($school['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Gender
            <select name="gender">
                <option value="">All genders</option>
                <?php foreach (['Female', 'Male', 'Other'] as $gender): ?>
                    <option value="<?= e($gender) ?>" <?= $genderFilter === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Grade
            <select name="grade">
                <option value="">All grades</option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?= e($grade) ?>" <?= $gradeFilter === $grade ? 'selected' : '' ?>><?= e($grade) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button secondary" type="submit">Search</button>
        <a class="button secondary" href="admin_learners.php">Clear</a>
    </form>
    <p class="empty">Showing <?= count($learners) ?> of <?= $total ?> learners.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Learner</th>
                    <th>School</th>
                    <th>Race</th>
                    <th>Grade</th>
                    <th>Gender</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($learners as $learner): ?>
                    <tr>
                        <td><?= e(trim($learner['first_name'] . ' ' . $learner['last_name'])) ?></td>
                        <td><?= e($learner['school_name']) ?></td>
                        <td><?= e($learner['race']) ?></td>
                        <td><?= e($learner['grade']) ?></td>
                        <td><?= e($learner['gender']) ?></td>
                        <td>
                            <a class="icon-button edit-link" href="admin_learners.php?<?= e($pageQueryWithPage) ?>&edit_id=<?= (int) $learner['id'] ?>#edit-learner" aria-label="Edit <?= e(trim($learner['first_name'] . ' ' . $learner['last_name'])) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 16.5V20h3.5L18.1 9.4l-3.5-3.5L4 16.5z"></path>
                                    <path d="M16 4.5 19.5 8"></path>
                                </svg>
                            </a>
                        </td>
                        <td>
                            <form method="post" class="table-action-form">
                                <input type="hidden" name="action" value="delete_learner">
                                <input type="hidden" name="id" value="<?= (int) $learner['id'] ?>">
                                <input type="hidden" name="return_query" value="<?= e($pageQueryWithPage) ?>">
                                <button class="icon-button delete" type="submit" aria-label="Delete <?= e(trim($learner['first_name'] . ' ' . $learner['last_name'])) ?>">X</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($selectedLearner): ?>
        <form method="post" class="inline-edit-panel" id="edit-learner">
            <input type="hidden" name="action" value="update_learner">
            <input type="hidden" name="id" value="<?= (int) $selectedLearner['id'] ?>">
            <input type="hidden" name="return_query" value="<?= e($pageQueryWithPage) ?>">
            <div class="panel-heading tight">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h2><?= e(trim($selectedLearner['first_name'] . ' ' . $selectedLearner['last_name'])) ?></h2>
                </div>
                <a class="button secondary" href="admin_learners.php?<?= e($pageQueryWithPage) ?>">Close</a>
            </div>
            <div class="edit-fields learner-edit-fields">
                <label>
                    School
                    <select name="school_id" required>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= (int) $school['id'] ?>" <?= (int) $selectedLearner['school_id'] === (int) $school['id'] ? 'selected' : '' ?>>
                                <?= e($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Name<input name="learner[first_name]" value="<?= e($selectedLearner['first_name']) ?>" required></label>
                <label>Surname<input name="learner[last_name]" value="<?= e($selectedLearner['last_name']) ?>" required></label>
                <label>Race<input name="learner[race]" value="<?= e($selectedLearner['race']) ?>"></label>
                <label>Grade<input name="learner[grade]" value="<?= e($selectedLearner['grade']) ?>" required></label>
                <label>
                    Gender
                    <select name="learner[gender]" required>
                        <option value="">Select</option>
                        <?php foreach (['Female', 'Male', 'Other'] as $gender): ?>
                            <option value="<?= e($gender) ?>" <?= $selectedLearner['gender'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="actions">
                    <button class="button" type="submit">Save Changes</button>
                </div>
            </div>
        </form>
    <?php elseif ($editId > 0): ?>
        <div class="notice error">That learner entry could not be found.</div>
    <?php endif; ?>

    <div class="admin-nav">
        <?php if ($page > 1): ?>
            <a class="button secondary" href="admin_learners.php?page=<?= $page - 1 ?>&<?= e($pageQuery) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a class="button secondary" href="admin_learners.php?page=<?= $page + 1 ?>&<?= e($pageQuery) ?>">Next</a>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
