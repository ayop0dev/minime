import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'minime admin',
  description: 'Admin panel for minime WordPress plugin',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
