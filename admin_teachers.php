<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Teacher Entries';
$pdo = db();
$perPage = 80;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$query = trim($_GET['q'] ?? '');
$schoolFilter = max(0, (int) ($_GET['school_id'] ?? 0));
$genderFilter = trim($_GET['gender'] ?? '');
$editId = max(0, (int) ($_GET['edit_id'] ?? 0));

if (!in_array($genderFilter, ['', 'Female', 'Male', 'Other'], true)) {
    $genderFilter = '';
}

function validTeacherUpdate(array $row): bool
{
    return trim($row['first_name'] ?? '') !== ''
        && trim($row['last_name'] ?? '') !== '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnQuery = trim($_POST['return_query'] ?? '');
    $returnUrl = 'admin_teachers.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');

    if ($action === 'update_teacher') {
        $id = (int) ($_POST['id'] ?? 0);
        $schoolId = (int) ($_POST['school_id'] ?? 0);
        $teacher = $_POST['teacher'] ?? [];

        if ($id < 1 || $schoolId < 1 || !is_array($teacher) || !validTeacherUpdate($teacher)) {
            flash('Name, surname, and school are required.', 'error');
            redirect($returnUrl);
        }

        $gender = trim($teacher['gender'] ?? '');
        if (!in_array($gender, ['Female', 'Male', 'Other'], true)) {
            $gender = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE teachers
             SET school_id = ?, first_name = ?, last_name = ?, subject = ?, race = ?, gender = ?, email = ?, phone = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $schoolId,
            trim($teacher['first_name']),
            trim($teacher['last_name']),
            trim($teacher['subject'] ?? '') ?: null,
            trim($teacher['race'] ?? '') ?: null,
            $gender,
            trim($teacher['email'] ?? '') ?: null,
            formatPhone(trim($teacher['phone'] ?? '')) ?: null,
            $id,
        ]);
        flash('Teacher updated.');
        redirect($returnUrl);
    }

    if ($action === 'delete_teacher') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM teachers WHERE id = ?');
            $stmt->execute([$id]);
            flash('Teacher deleted.');
        }
        redirect($returnUrl);
    }
}

$schools = $pdo->query('SELECT id, name FROM schools ORDER BY name')->fetchAll();
$subjects = $pdo->query('SELECT DISTINCT subject FROM teachers WHERE subject IS NOT NULL AND subject <> "" ORDER BY subject')->fetchAll(PDO::FETCH_COLUMN);
$filters = [];
$params = [];

if ($query !== '') {
    $filters[] = '(t.first_name LIKE :q OR t.last_name LIKE :q OR s.name LIKE :q OR t.subject LIKE :q OR t.race LIKE :q)';
    $params[':q'] = '%' . $query . '%';
}

if ($schoolFilter > 0) {
    $filters[] = 't.school_id = :school_id';
    $params[':school_id'] = $schoolFilter;
}

if ($genderFilter !== '') {
    $filters[] = 't.gender = :gender';
    $params[':gender'] = $genderFilter;
}

$whereClause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM teachers t
     LEFT JOIN schools s ON s.id = t.school_id
     ' . $whereClause
);
foreach ($params as $name => $value) {
    $countStmt->bindValue($name, $value, $name === ':school_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT t.id, t.school_id, t.first_name, t.last_name, t.subject, t.race, t.gender, t.email, t.phone, s.name AS school_name
     FROM teachers t
     LEFT JOIN schools s ON s.id = t.school_id
     ' . $whereClause . '
     ORDER BY t.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value, $name === ':school_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$teachers = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));
$pageQuery = http_build_query([
    'q' => $query,
    'school_id' => $schoolFilter ?: null,
    'gender' => $genderFilter ?: null,
]);
$pageQueryWithPage = http_build_query([
    'page' => $page,
    'q' => $query,
    'school_id' => $schoolFilter ?: null,
    'gender' => $genderFilter ?: null,
]);
$selectedTeacher = null;

if ($editId > 0) {
    $editStmt = $pdo->prepare(
        'SELECT t.id, t.school_id, t.first_name, t.last_name, t.subject, t.race, t.gender, t.email, t.phone, s.name AS school_name
         FROM teachers t
         LEFT JOIN schools s ON s.id = t.school_id
         WHERE t.id = ?'
    );
    $editStmt->execute([$editId]);
    $selectedTeacher = $editStmt->fetch();
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Filters</p>
            <h2>Teacher Entries</h2>
        </div>
        <div class="panel-actions">
    <a class="button secondary" href="admin.php">Back to Overview</a>

    <a class="button" href="admin_export.php?type=teachers">
    Download Teachers Excel
</a>
</div>
    </div>

    <form method="get" class="form">
        <div class="grid">
            <label>
                Search
                <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search by name, surname, subject, or race">
            </label>
            <label>
                School
                <select name="school_id">
                    <option value="">All schools</option>
                    <?php foreach ($schools as $school): ?>
                        <option value="<?= (int) $school['id'] ?>" <?= $schoolFilter === (int) $school['id'] ? 'selected' : '' ?>><?= e($school['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Gender
                <select name="gender">
                    <option value="">All genders</option>
                    <option value="Female" <?= $genderFilter === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Male" <?= $genderFilter === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Other" <?= $genderFilter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </label>
        </div>
        <button class="button" type="submit">Apply Filters</button>
    </form>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Results</p>
            <h2>Teachers (<?= $total ?> total)</h2>
        </div>
    </div>

    <?php if (!$teachers): ?>
        <p class="empty">No teachers found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>School</th>
                        <th>Subject</th>
                        <th>Race</th>
                        <th>Gender</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?= e($teacher['first_name']) ?></td>
                            <td><?= e($teacher['last_name']) ?></td>
                            <td><?= e($teacher['school_name']) ?></td>
                            <td><?= e($teacher['subject']) ?></td>
                            <td><?= e($teacher['race']) ?></td>
                            <td><?= e($teacher['gender']) ?></td>
                            <td><?= e($teacher['email']) ?></td>
                            <td><?= e($teacher['phone']) ?></td>
                            <td><a href="?edit_id=<?= (int) $teacher['id'] ?>&<?= $pageQuery ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a class="button secondary" href="?page=<?= $page - 1 ?>&<?= $pageQuery ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="button secondary" href="?page=<?= $page + 1 ?>&<?= $pageQuery ?>">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($selectedTeacher): ?>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Edit</p>
                <h2>Teacher Details</h2>
            </div>
        </div>

        <form method="post" class="form">
            <input type="hidden" name="action" value="update_teacher">
            <input type="hidden" name="id" value="<?= (int) $selectedTeacher['id'] ?>">
            <input type="hidden" name="return_query" value="<?= e($pageQueryWithPage) ?>">

            <div class="grid two">
                <label>
                    Name
                    <input name="teacher[first_name]" value="<?= e($selectedTeacher['first_name']) ?>" required>
                </label>
                <label>
                    Surname
                    <input name="teacher[last_name]" value="<?= e($selectedTeacher['last_name']) ?>" required>
                </label>
                <label>
                    School
                    <select name="school_id" required>
                        <option value="">Select school</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= (int) $school['id'] ?>" <?= $selectedTeacher['school_id'] === (int) $school['id'] ? 'selected' : '' ?>><?= e($school['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Subject
                    <input name="teacher[subject]" value="<?= e($selectedTeacher['subject'] ?? '') ?>">
                </label>
                <label>
                    Race
                    <select name="teacher[race]">
                        <option value="">Select</option>
                        <option value="African" <?= $selectedTeacher['race'] === 'African' ? 'selected' : '' ?>>African</option>
                        <option value="Coloured" <?= $selectedTeacher['race'] === 'Coloured' ? 'selected' : '' ?>>Coloured</option>
                        <option value="Indian" <?= $selectedTeacher['race'] === 'Indian' ? 'selected' : '' ?>>Indian</option>
                        <option value="White" <?= $selectedTeacher['race'] === 'White' ? 'selected' : '' ?>>White</option>
                        <option value="Other" <?= $selectedTeacher['race'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </label>
                <label>
                    Gender
                    <select name="teacher[gender]">
                        <option value="">Select</option>
                        <option value="Female" <?= $selectedTeacher['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Male" <?= $selectedTeacher['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Other" <?= $selectedTeacher['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </label>
                <label>
                    Email
                    <input type="email" name="teacher[email]" value="<?= e($selectedTeacher['email'] ?? '') ?>">
                </label>
                <label>
                    Phone
                    <input name="teacher[phone]" value="<?= e($selectedTeacher['phone'] ?? '') ?>">
                </label>
            </div>

            <div class="actions">
                <button class="button" type="submit">Save Changes</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete_teacher">
                    <input type="hidden" name="id" value="<?= (int) $selectedTeacher['id'] ?>">
                    <input type="hidden" name="return_query" value="<?= e($pageQuery) ?>">
                    <button class="button secondary" type="submit" onclick="return confirm('Delete this teacher? This action cannot be undone.')">Delete</button>
                </form>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
