import type { Metadata } from "next";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "HORIZONS ExoClick MCP",
  description: "Private ExoClick API v2 MCP bridge for HORIZONS"
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body style={{ fontFamily: "system-ui, sans-serif", maxWidth: 900, margin: "40px auto", padding: "0 20px", lineHeight: 1.55 }}>
        {children}
      </body>
    </html>
  );
}
