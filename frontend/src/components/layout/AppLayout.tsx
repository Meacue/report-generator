import { Link, Outlet, useLocation } from "react-router-dom";

const navItems = [
  { path: "/", label: "Панель" },
  { path: "/inbox", label: "Входящие" },
  { path: "/reports", label: "Отчёты" },
  { path: "/projects", label: "Проекты" },
  { path: "/settings", label: "Настройки" },
];

export function AppLayout() {
  const location = useLocation();

  return (
    <div style={{ display: "flex", minHeight: "100vh" }}>
      <nav
        style={{
          width: "220px",
          background: "#1a1a2e",
          color: "#fff",
          padding: "20px 0",
        }}
      >
        <h2
          style={{
            textAlign: "center",
            margin: "0 0 30px",
            fontSize: "16px",
          }}
        >
          Report Generator
        </h2>
        <ul style={{ listStyle: "none", padding: 0, margin: 0 }}>
          {navItems.map((item) => (
            <li key={item.path}>
              <Link
                to={item.path}
                style={{
                  display: "block",
                  padding: "12px 24px",
                  color: location.pathname === item.path ? "#646cff" : "#ccc",
                  textDecoration: "none",
                  fontWeight:
                    location.pathname === item.path ? "bold" : "normal",
                }}
              >
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
      </nav>
      <main style={{ flex: 1, padding: "24px" }}>
        <Outlet />
      </main>
    </div>
  );
}
