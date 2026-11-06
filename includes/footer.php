</main>

<footer class="site-footer">
    <div class="container footer-main">
        <div class="footer-brand">
            <a class="brand brand-footer" href="<?= e(url('index.php')) ?>">
                <img class="brand-mark" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="36" height="36">
                <span class="brand-wordmark">Luma<span>Stay</span></span>
            </a>
            <p>Alojamentos escolhidos um a um, para viagens que ficam na memória.</p>
            <p class="footer-note">Projeto académico de demonstração.<br>Reservas simuladas, sem pagamentos reais.</p>
        </div>

        <div class="footer-column">
            <h2>Explorar</h2>
            <ul>
                <li><a href="<?= e(url('index.php')) ?>">Início</a></li>
                <li><a href="<?= e(url('alojamentos.php')) ?>">Todos os alojamentos</a></li>
                <li><a href="<?= e(url('consultar-reserva.php')) ?>">Consultar reserva</a></li>
                <li><a href="<?= e(url('index.php')) ?>#destinos">Destinos</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h2>A LumaStay</h2>
            <ul>
                <li><a href="<?= e(url('index.php')) ?>#sobre">A nossa curadoria</a></li>
                <li><a href="<?= e(url('index.php')) ?>#vantagens">Porque escolher-nos</a></li>
                <li><a href="<?= e(url('login.php')) ?>">Área reservada</a></li>
                <li><a href="<?= e(url('admin/index.php')) ?>">Administração</a></li>
            </ul>
        </div>

        <div class="footer-column footer-contact">
            <h2>Contacto</h2>
            <ul>
                <li><?= icon('mail') ?><span>reservas@lumastay.pt</span></li>
                <li><?= icon('phone') ?><span>+351 210 000 240</span></li>
                <li><?= icon('pin') ?><span>Lisboa, Portugal</span></li>
            </ul>
        </div>
    </div>

    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> LumaStay. Todos os direitos reservados.</p>
        <p>Conceito, design e desenvolvimento originais.</p>
    </div>
</footer>

<script src="<?= e(asset('js/main.js')) ?>?v=1.0" defer></script>
</body>
</html>
