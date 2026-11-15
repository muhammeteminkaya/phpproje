<?php
/**
 * Ortak sayfa alt kısmı: ana alanın kapanışı, alt bilgi ve tema betiği.
 */
?>
        <footer class="alt-bilgi">
            <span>BütçeTakip · İnternet Programcılığı I · 2026</span>
            <span>Tüm girdiler <code>htmlspecialchars()</code> ile ekrana yazdırılır.</span>
        </footer>
    </main>
</div>

<script>
// Gece/gündüz teması: seçim tarayıcıda (localStorage) saklanır
function temaDegistir() {
    var gece = document.documentElement.classList.toggle('gece');
    localStorage.setItem('tema', gece ? 'gece' : 'gunduz');
}
</script>
</body>
</html>
