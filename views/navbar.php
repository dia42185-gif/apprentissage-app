<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid px-3">
    <a class="navbar-brand" href="index.php">Mon apprentissage</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="today.php">Aujourd'hui</a></li>
        <li class="nav-item"><a class="nav-link" href="planning.php">Planning</a></li>
        <li class="nav-item"><a class="nav-link" href="formations.php">Formations</a></li>
        <li class="nav-item"><a class="nav-link" href="goals.php">Objectifs</a></li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            Plus
          </a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="time_tracking.php">Temps d'apprentissage</a></li>
            <li><a class="dropdown-item" href="pomodoro.php">Pomodoro</a></li>
            <li><a class="dropdown-item" href="revisions.php">Revisions</a></li>
            <li><a class="dropdown-item" href="journal.php">Journal</a></li>
            <li><a class="dropdown-item" href="vacation.php">Vacances</a></li>
            <li><a class="dropdown-item" href="stats.php">Statistiques</a></li>
          </ul>
        </li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="nav-link text-white-50">
            <?= isset($_SESSION['user_name']) ? e($_SESSION['user_name']) : '' ?>
          </span>
        </li>
        <li class="nav-item">
          <form method="POST" action="logout.php" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" class="nav-link border-0 bg-transparent">Deconnexion</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</nav>