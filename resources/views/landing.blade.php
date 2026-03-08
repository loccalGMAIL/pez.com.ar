<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PEZ | Servicios IT — Soporte técnico y diseño web en Argentina</title>
    <meta name="description" content="PEZ Servicios IT — Soporte técnico, redes, WiFi y diseño web para empresas en Argentina. Atención real, soluciones a medida. Respondemos en menos de 24 horas.">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://pez.com.ar">
    <meta property="og:title" content="PEZ | Servicios IT — Soporte técnico y diseño web">
    <meta property="og:description" content="Soporte técnico, redes y diseño web para empresas. Un equipo IT que te acompaña de verdad. Respondemos en menos de 24 horas.">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="PEZ | Servicios IT">
    <meta name="twitter:description" content="Soporte técnico, redes y diseño web para empresas argentinas. Respondemos en menos de 24 horas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <header class="site-header">
        <div class="maxed">
            <div class="brand">
                <span class="brand-mark">
                    <img src="{{ asset('foto-de-perfil-A85EQ4bqJ4CWQP9N.png') }}" alt="PEZ">
                </span>
            </div>
            <button class="nav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav-links">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <nav class="nav-links" id="nav-links">
                <a href="#inicio" data-section="inicio" class="active">Inicio</a>
                <a href="#servicios" data-section="servicios">Servicios IT</a>
                <a href="#proyectos" data-section="proyectos">Soluciones Web</a>
                <a href="#metodo" data-section="metodo">Cómo trabajamos</a>
                <a href="#contacto" data-section="contacto" class="outline">Contacto</a>
            </nav>
        </div>
    </header>

    <main>
        <section id="inicio" class="hero">
            <div class="maxed hero-grid">
                <div class="hero-copy hero-it">
                    <p class="eyebrow">Soluciones IT</p>
                    <h1>Un equipo IT que te acompaña de verdad.</h1>
                    <p class="lead">Soluciones informaticas para que tu empresa trabaje sin cortes. Soporte, mantenimiento y mejoras continuas.</p>
                    <div class="actions">
                        <a class="btn primary" href="#contacto">Agendar llamada</a>
                        <a class="btn ghost" href="#servicios">Ver servicios IT</a>
                    </div>
                    <div class="pills">
                        <span>Soporte tecnico</span>
                        <span>Redes &amp; wifi</span>
                    </div>
                </div>
                <div class="hero-divider" aria-hidden="true"></div>
                <div class="hero-copy hero-web">
                    <p class="eyebrow">Diseño web</p>
                    <h1>Webs claras que representan tu marca.</h1>
                    <p class="lead">Webs corporativas y landings con contenido ordenado.</p>
                    <div class="actions">
                        <a class="btn primary" href="#contacto">Pedir propuesta</a>
                        <a class="btn ghost" href="#proyectos">Ver soluciones Web</a>
                    </div>
                    <div class="pills">
                        <span>Web institucional</span>
                        <span>Landings</span>
                        <span>Sistemas en linea</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="servicios" class="section">
            <div class="maxed">
                <div class="section-head reveal">
                    <p class="eyebrow">Servicios IT</p>
                    <h2>Soluciones informáticas completas.</h2>
                </div>
                <div class="services-grid">
                    <article class="web-card reveal web-card--it">
                        <div class="web-card__icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="4" y="4" width="24" height="18" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M4 16h24" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 28h8M16 22v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M10 10h2M14 10h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="web-card__content">
                            <p class="web-card__label">Soporte IT</p>
                            <h3>Tu infraestructura, siempre en marcha.</h3>
                            <ul class="web-card__features">
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Help desk remoto y on-site
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Mantenimiento preventivo de PCs y notebooks
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Resolución de incidentes diarios
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Seguimiento y reporte de cada caso
                                </li>
                            </ul>
                        </div>
                    </article>
                    <article class="web-card reveal web-card--it">
                        <div class="web-card__icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="16" cy="16" r="4" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="4" cy="8" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="28" cy="8" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="4" cy="24" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="28" cy="24" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M6.5 9.5L12 13M20 13l5.5-4.5M6.5 22.5L12 19M20 19l5.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="web-card__content">
                            <p class="web-card__label">Redes &amp; conectividad</p>
                            <h3>Red sólida, WiFi sin puntos ciegos.</h3>
                            <ul class="web-card__features">
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Diseño y configuración de redes LAN/WiFi
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Routers, switches y access points
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Monitoreo y alertas de conectividad
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Optimización para oficinas y depósitos
                                </li>
                            </ul>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="proyectos" class="section soft">
            <div class="maxed">
                <div class="section-head reveal">
                    <p class="eyebrow">Soluciones Web</p>
                    <h2>Presencia digital y sistemas a tu medida.</h2>
                </div>
                <div class="web-services-grid">

                    <article class="web-card reveal web-card--design">
                        <div class="web-card__icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="2" y="5" width="28" height="19" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M2 10h28" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="6.5" cy="7.5" r="1" fill="currentColor"/>
                                <circle cx="10" cy="7.5" r="1" fill="currentColor"/>
                                <circle cx="13.5" cy="7.5" r="1" fill="currentColor"/>
                                <path d="M10 28h12M16 24v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M8 15l3 3-3 3M14 18h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div class="web-card__content">
                            <p class="web-card__label">Diseño web</p>
                            <h3>Tu marca, clara y confiable en la web.</h3>
                            <ul class="web-card__features">
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Webs corporativas e institucionales
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Landings orientadas a conversión
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Performance y base SEO incluidos
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Diseño responsive para todos los dispositivos
                                </li>
                            </ul>
                        </div>
                    </article>

                    <article class="web-card reveal web-card--systems">
                        <div class="web-card__icon">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect x="2" y="2" width="12" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="18" y="2" width="12" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="2" y="18" width="12" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="18" y="18" width="12" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M14 8h4M8 14v4M24 14v4M14 24h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="web-card__content">
                            <p class="web-card__label">Sistemas en línea</p>
                            <h3>Digitalizá procesos y ordená tu operación.</h3>
                            <ul class="web-card__features">
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Intranets y portales internos
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Paneles de gestión y reportes
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Flujos de aprobación y permisos por rol
                                </li>
                                <li>
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7l3.5 3.5L12 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Digitalización de procesos operativos
                                </li>
                            </ul>
                        </div>
                    </article>

                </div>
            </div>
        </section>

        <section id="metodo" class="section">
            <div class="maxed">
                <div class="section-head reveal">
                    <p class="eyebrow">Cómo trabajamos</p>
                    <h2>Así trabajamos con vos.</h2>
                </div>
                <div class="steps-grid">
                    <div class="step-card reveal">
                        <div class="step-card__number">01</div>
                        <h3>Relevamiento</h3>
                        <p>Escuchamos tu situación, mapeamos procesos y definimos prioridades antes de escribir una sola línea.</p>
                    </div>
                    <div class="step-card reveal">
                        <div class="step-card__number">02</div>
                        <h3>Diseño y UX</h3>
                        <p>Armamos la solución con la identidad de tu marca para que todo se vea unificado y sea fácil de usar.</p>
                    </div>
                    <div class="step-card reveal">
                        <div class="step-card__number">03</div>
                        <h3>Implementación</h3>
                        <p>Desarrollamos e instalamos la solución con accesos, roles y flujos acordados en la etapa anterior.</p>
                    </div>
                    <div class="step-card reveal">
                        <div class="step-card__number">04</div>
                        <h3>Adopción y soporte</h3>
                        <p>Acompañamos al equipo en el arranque y seguimos disponibles para ajustes y soporte continuo.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section cta">
            <div class="maxed cta-box reveal">
                <div>
                    <p class="eyebrow">Listo para ordenar tu IT</p>
                    <h2>Un plan claro para que tu equipo trabaje sin cortes.</h2>
                    <p class="section-copy">Contanos tu situación y en menos de 48 horas te proponemos un plan con prioridades y próximos pasos.</p>
                </div>
                <div class="cta-actions">
                    <a class="btn primary" href="#contacto">Quiero empezar</a>
                    <a class="btn ghost" href="mailto:info@pez.com.ar">info@pez.com.ar</a>
                </div>
            </div>
        </section>

        <section id="contacto" class="section contact">
            <div class="maxed contact-grid">
                <div class="contact-copy reveal">
                    <p class="eyebrow">Contacto</p>
                    <h2>Hablemos de tu infraestructura.</h2>
                    <p>Respondemos en menos de 24h.</p>
                    <div class="contact-cards">
                        <div class="mini-card floating">
                            <div class="mini-label">Email</div>
                            <div class="mini-value">info@pez.com.ar</div>
                        </div>
                        <div class="mini-card floating delay">
                            <div class="mini-label">Teléfono</div>
                            <div class="mini-value">+54 9 3541 549 674</div>
                        </div>
                    </div>
                </div>
                <form class="contact-form reveal" action="{{ route('contacto.send') }}" method="POST">
                    @csrf
                    <div class="form-row">
                        <label for="nombre">Nombre</label>
                        <input id="nombre" name="nombre" type="text" placeholder="¿Cómo te llamás?" required>
                    </div>
                    <div class="form-row">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="email@tuempresa.com" required>
                    </div>
                    <div class="form-row">
                        <label for="mensaje">Mensaje</label>
                        <textarea id="mensaje" name="mensaje" rows="4" placeholder="Contanos en qué podemos ayudar" required></textarea>
                    </div>
                    <button type="submit" class="btn primary full">Enviar mensaje</button>
                </form>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="maxed footer-grid">
            <div>
                <div class="brand-mark footer-logo">
                    <img src="{{ asset('foto-de-perfil-A85EQ4bqJ4CWQP9N.png') }}" alt="PEZ Servicios IT">
                </div>
                <p>Servicios IT con foco en continuidad y soporte real.</p>
            </div>
            <div class="footer-links">
                <a href="#inicio">Inicio</a>
                <a href="#servicios">Servicios</a>
                <a href="#proyectos">Soluciones</a>
                <a href="#contacto">Contacto</a>
            </div>
            <div class="footer-meta">
                <span>&copy; 2026 PEZ</span>
                <span>Soporte IT</span>
            </div>
        </div>
    </footer>

    {{-- Botón flotante de WhatsApp --}}
    <a class="whatsapp-fab" href="http://wa.me/+5493541549674" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M14 1.75C7.235 1.75 1.75 7.235 1.75 14c0 2.17.577 4.202 1.585 5.953L1.75 26.25l6.473-1.558A12.197 12.197 0 0014 26.25c6.765 0 12.25-5.485 12.25-12.25S20.765 1.75 14 1.75z" fill="currentColor" fill-opacity=".15" stroke="currentColor" stroke-width="1.5"/>
            <path d="M19.25 16.625c-.292-.146-1.726-.851-1.993-.948-.266-.097-.46-.146-.653.146-.194.292-.75.948-.919 1.142-.169.194-.338.218-.63.073-.292-.146-1.232-.454-2.347-1.448-.867-.773-1.452-1.728-1.622-2.02-.169-.292-.018-.45.127-.595.13-.13.292-.34.438-.51.146-.169.194-.292.292-.486.097-.194.049-.365-.025-.51-.073-.146-.653-1.574-.894-2.156-.236-.567-.475-.49-.653-.499l-.557-.01c-.194 0-.51.073-.777.365-.267.292-1.02 .997-1.02 2.43 0 1.434 1.044 2.819 1.19 3.013.146.194 2.054 3.137 4.978 4.399.696.3 1.238.48 1.661.614.698.222 1.334.19 1.836.116.56-.084 1.726-.706 1.97-1.388.243-.681.243-1.265.17-1.388-.073-.122-.267-.194-.559-.34z" fill="currentColor"/>
        </svg>
    </a>

</body>
</html>
