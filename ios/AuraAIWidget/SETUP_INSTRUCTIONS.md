# Widget Extension Setup - Step by Step

## Step 1: Create Widget Extension Target in Xcode

1. In Xcode, go to **File → New → Target**
2. Select **"Widget Extension"**
3. In the dialog that appears:
   - **Product Name:** `AuraAIWidget`
   - **Team:** Select your Apple Developer team (or "Add account..." if needed)
   - **Organization Identifier:** `app.auraai.app` (should auto-fill)
   - **Bundle Identifier:** Should auto-fill as `app.auraai.app.AuraAIWidget`
   - **Include Live Activity:** ✅ Check this
   - **Include Control:** ✅ Check this  
   - **Include Configuration App Intent:** ✅ Check this
   - **Project:** Should show "AuraAI"
   - **Embed in Application:** Should show "AuraAI" ✅ checked
4. Click **"Finish"**

## Step 2: Replace Generated Files

After Xcode creates the widget extension, it will generate default Swift files. You need to **replace** them with the custom files I created:

### Files to Replace:

1. **AuraAIWidget.swift**
   - Xcode will create: `ios/AuraAIWidget/AuraAIWidget.swift`
   - **Replace** its contents with the file I created at `ios/AuraAIWidget/AuraAIWidget.swift`
   - This file contains the actual widget UI and logic

2. **AuraAIWidgetBundle.swift** (or similar name)
   - Xcode might create: `ios/AuraAIWidget/AuraAIWidgetBundle.swift` or `AuraAIWidgetBundle.swift`
   - **Replace** its contents with the file I created at `ios/AuraAIWidget/AuraAIWidgetBundle.swift`

3. **Info.plist**
   - Xcode will create: `ios/AuraAIWidget/Info.plist`
   - **Replace** its contents with the file I created at `ios/AuraAIWidget/Info.plist`

4. **AuraAIWidget.entitlements**
   - Xcode will create: `ios/AuraAIWidget/AuraAIWidget.entitlements`
   - **Replace** its contents with the file I created at `ios/AuraAIWidget/AuraAIWidget.entitlements`

### How to Replace:

**Option A: Copy-Paste**
1. Open the file Xcode generated
2. Select all (Cmd+A)
3. Delete
4. Open the file I created (in your file system or in another editor)
5. Copy all contents
6. Paste into the Xcode file
7. Save

**Option B: File Replacement**
1. Close Xcode
2. In Finder, navigate to `ios/AuraAIWidget/`
3. Replace the generated files with the ones I created
4. Reopen Xcode

## Step 3: Add Native Module Files to Main App Target

Make sure these files are added to the **AuraAI** (main app) target, NOT the widget target:

1. **WidgetDataManager.swift** - Should be at `ios/AuraAI/WidgetDataManager.swift`
2. **WidgetDataManagerBridge.m** - Should be at `ios/AuraAI/WidgetDataManagerBridge.m`

To verify:
- Select each file in Xcode
- Check the "Target Membership" in the File Inspector (right panel)
- Make sure only **AuraAI** is checked (NOT AuraAIWidget)

## Step 4: Configure App Groups

1. Select the **AuraAI** target (main app)
2. Go to **Signing & Capabilities** tab
3. Click **"+ Capability"**
4. Add **"App Groups"**
5. Check the box for: `group.app.auraai.app`
6. Repeat steps 1-5 for the **AuraAIWidget** target

## Step 5: Build and Test

1. Select the **AuraAI** scheme (main app)
2. Build and run: **Product → Run** (Cmd+R)
3. On your device/simulator:
   - Long press on home screen
   - Tap the "+" button (top left)
   - Search for "Aura AI"
   - Add the widget
   - Or add it to Notification Center by swiping down

## Troubleshooting

- **"No such module 'WidgetKit'"**: Make sure deployment target is iOS 17.0+
- **"App Group not found"**: Configure App Groups in Apple Developer Portal
- **Widget not updating**: Check that WidgetDataManager is properly bridged

