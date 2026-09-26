<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Unified Public Portal Footer Component
 * Shared across all public portal pages (index.php, apply.php, track.php, etc.)
 */
?>
<?php
$isLandingPage = in_array(preg_replace('/\.php$/', '', basename($_SERVER['PHP_SELF'] ?? '')), ['index', '']);
?>
<?php if ($isLandingPage): ?>
<style>
/* Unified Public Portal Footer Styles */
.portal-footer {
    background: #082010;
    color: #94a3b8;
    padding: 3rem 2rem 1.5rem;
    font-size: 0.84rem;
    border-top: none;
    margin-top: auto;
    width: 100%;
}

.footer-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 2.5rem;
    margin-bottom: 2.5rem;
}

.footer-col h4 {
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-col ul li {
    margin-bottom: 0.5rem;
}

.footer-col ul li a {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.2s;
}

.footer-col ul li a:hover {
    color: var(--nis-gold, #d4af37);
}

.footer-bottom {
    max-width: 1200px;
    margin: 0 auto;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.78rem;
    color: #ffffff;
    font-weight: 400;
}

@media (max-width: 768px) {
    .portal-footer {
        padding: 2.5rem 1.5rem 1.25rem;
    }
    .footer-inner {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .footer-bottom {
        flex-direction: column;
        text-align: center;
        justify-content: center;
    }
}
</style>

<!-- Full Landing Page Footer -->
<footer class="portal-footer">
    <div class="footer-inner">
        <div class="footer-col">
            <h4>Nigeria Immigration Service</h4>
            <p style="line-height: 1.6; margin-bottom: 1rem;">
                Federal Republic of Nigeria. Dedicated to the security, integrity, and lawful residency administration for all expatriates and foreign nationals.
            </p>
            <div style="font-size: 0.8rem; color: var(--nis-gold, #d4af37);">
                <i class="fas fa-check-circle"></i> Official e-Services Portal
            </div>
        </div>

        <div class="footer-col">
            <h4>Quick Services</h4>
            <ul>
                <li><a href="apply"><i class="fas fa-angle-right"></i> Online Residence Application</a></li>
                <li><a href="track"><i class="fas fa-angle-right"></i> Track Application Status</a></li>
                <li><a href="track?action=slip"><i class="fas fa-angle-right"></i> Print Approved Biometrics Slip</a></li>
                <li><a href="apply?type=renewal"><i class="fas fa-angle-right"></i> Renew Residence Card</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Support &amp; Guidelines</h4>
            <ul>
                <li><i class="fas fa-info-circle"></i> Standard Validity: 24 Months</li>
                <li><i class="fas fa-passport"></i> Minimum 6 Months Passport Validity</li>
                <li><i class="fas fa-clock"></i> Enrollment: Monday – Friday, 8:00 AM – 4:00 PM</li>
                <li><i class="fas fa-envelope"></i> inquiries@immigration.gov.ng</li>
                <li><a href="privacy-policy" style="color: #94a3b8;"><i class="fas fa-user-shield"></i> Privacy Policy &amp; Data Protection</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div>
            &copy; <?php echo date('Y'); ?> Nigeria Immigration Service (NIS). All rights reserved.
        </div>
        <div>
            <a href="privacy-policy" style="color: #94a3b8; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#d4af37'" onmouseout="this.style.color='#94a3b8'">Privacy Policy</a>
        </div>
    </div>
</footer>
<?php else: ?>
<!-- Minimal Footer for Non-Landing Pages -->
<style>
.portal-footer-minimal {
    padding: 1.5rem 1rem;
    text-align: center;
    font-size: 0.85rem;
    color: #ffffff !important;
    font-weight: normal;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.85);
    margin-top: auto;
    width: 100%;
    background: transparent !important;
}
</style>
<footer class="portal-footer-minimal">
    &copy; <?php echo date('Y'); ?> Nigeria Immigration Service (NIS). All rights reserved.
</footer>
<?php endif; ?>
